<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use DOMXPath;

/**
 * Converts stored post HTML into the read-only views defined in Blog MCP v2
 * section 3: text, markdown, paragraphs, word count and reading time.
 *
 * Characters are returned exactly as stored: no Unicode normalization, and
 * harakat, shadda, tatweel and direction marks are kept. The `text` view is
 * the same text the edit engine matches against.
 */
final class ContentViews
{
    /** @var list<string> Tags that form one block each in document order. */
    private const array BLOCK_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'figcaption', 'pre', 'td', 'th',
    ];

    private const int WORDS_PER_MINUTE = 200;

    /**
     * Plain text: entities decoded, tags removed, one block per paragraph,
     * blocks separated by a blank line.
     */
    public function toText(string $html): string
    {
        return implode(
            "\n\n",
            array_map(fn (array $block): string => $block['text'], $this->blocks($html)),
        );
    }

    /**
     * Headings, bold, italic, links, lists and quotes as Markdown.
     * Alignment styles and other block attributes are dropped.
     */
    public function toMarkdown(string $html): string
    {
        return implode(
            "\n\n",
            array_map(fn (array $block): string => $this->blockToMarkdown($block), $this->blocks($html)),
        );
    }

    /**
     * Every top-level block with its 1-based index in document order.
     *
     * @return list<array{index: int, type: string, text: string, html: string}>
     */
    public function toParagraphs(string $html): array
    {
        $paragraphs = [];

        foreach ($this->blocks($html, withHtml: true) as $position => $block) {
            $paragraphs[] = [
                'index' => $position + 1,
                'type' => $block['type'],
                'text' => $block['text'],
                'html' => $block['html'],
            ];
        }

        return $paragraphs;
    }

    public function wordCount(string $html): int
    {
        $text = trim($this->toText($html));

        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    public function readingTimeMinutes(string $html): int
    {
        return max(1, (int) ceil($this->wordCount($html) / self::WORDS_PER_MINUTE));
    }

    /**
     * Parse the fragment and enumerate its top-level blocks in document order.
     *
     * @return list<array{type: string, text: string, html: string, node: DOMNode}>
     */
    private function blocks(string $html, bool $withHtml = false): array
    {
        $document = $this->parse($html);
        $blocks = [];
        $this->collectBlocks($document, $blocks);

        if ($withHtml) {
            $quotePlaceholder = $this->quotePlaceholder($html);
            $nbspPlaceholder = $this->nbspPlaceholder($html, $quotePlaceholder);
            $this->stashEntities($document, $quotePlaceholder, $nbspPlaceholder);

            foreach ($blocks as $position => $block) {
                $blocks[$position]['html'] = $this->serializeNode(
                    $document,
                    $block['node'],
                    $quotePlaceholder,
                    $nbspPlaceholder,
                );
            }
        }

        return $blocks;
    }

    /**
     * @param  list<array{type: string, text: string, html: string, node: DOMNode}>  $blocks
     */
    private function collectBlocks(DOMNode $node, array &$blocks): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->data) !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $child->data, 'html' => '', 'node' => $child];
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::BLOCK_TAGS, true)) {
                $blocks[] = ['type' => $tag, 'text' => $child->textContent, 'html' => '', 'node' => $child];

                continue;
            }

            if ($this->hasBlockDescendant($child)) {
                $this->collectBlocks($child, $blocks);

                continue;
            }

            if (trim($child->textContent) !== '') {
                $blocks[] = ['type' => $tag, 'text' => $child->textContent, 'html' => '', 'node' => $child];
            }
        }
    }

    private function hasBlockDescendant(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower($child->tagName), self::BLOCK_TAGS, true) || $this->hasBlockDescendant($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load an HTML fragment without adding html/body wrappers and without
     * mangling UTF-8: the XML processing instruction forces the encoding.
     */
    private function parse(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $document->preserveWhiteSpace = true;
            $document->formatOutput = false;
            $document->loadHTML(
                '<?xml encoding="UTF-8"?>'.$html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach (iterator_to_array($document->childNodes) as $child) {
            if ($child instanceof DOMProcessingInstruction) {
                $document->removeChild($child);
            }
        }

        return $document;
    }

    /**
     * Serialize one node, restoring the entity style the editor uses today:
     * `"` becomes `&quot;` and U+00A0 becomes `&nbsp;` inside text.
     */
    private function serializeNode(
        DOMDocument $document,
        DOMNode $node,
        string $quotePlaceholder,
        string $nbspPlaceholder,
    ): string {
        return str_replace(
            [$quotePlaceholder, $nbspPlaceholder],
            ['&quot;', '&nbsp;'],
            $document->saveHTML($node),
        );
    }

    /**
     * Replace `"` and U+00A0 in every text node with private-use placeholders
     * so libxml does not turn them into literal characters on serialization.
     */
    private function stashEntities(DOMDocument $document, string $quotePlaceholder, string $nbspPlaceholder): void
    {
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//text()') ?: [] as $textNode) {
            if ($textNode instanceof DOMText) {
                $textNode->data = str_replace(
                    ['"', "\u{00A0}"],
                    [$quotePlaceholder, $nbspPlaceholder],
                    $textNode->data,
                );
            }
        }
    }

    /**
     * A private-use character guaranteed absent from the source HTML, so it
     * can stand in for `"` during serialization.
     */
    private function quotePlaceholder(string $html): string
    {
        for ($code = 0xE000; $code <= 0xF8FE; $code++) {
            if (! str_contains($html, mb_chr($code))) {
                return mb_chr($code);
            }
        }

        return "\u{F8FF}";
    }

    private function nbspPlaceholder(string $html, string $quotePlaceholder): string
    {
        for ($code = 0xE000; $code <= 0xF8FE; $code++) {
            $candidate = mb_chr($code);

            if ($candidate !== $quotePlaceholder && ! str_contains($html, $candidate)) {
                return $candidate;
            }
        }

        return "\u{F8FF}";
    }

    /**
     * @param  array{type: string, text: string, html: string, node: DOMNode}  $block
     */
    private function blockToMarkdown(array $block): string
    {
        $node = $block['node'];

        return match ($block['type']) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => str_repeat('#', (int) substr($block['type'], 1)).' '.$this->inlineMarkdown($node),
            'li' => $node instanceof DOMElement ? $this->listItemMarkdown($node) : $this->inlineMarkdown($node),
            'blockquote' => $this->quoteMarkdown($node),
            'pre' => "```\n".$node->textContent."\n```",
            default => $this->inlineMarkdown($node),
        };
    }

    private function listItemMarkdown(DOMElement $item): string
    {
        $depth = 0;
        $marker = '-';
        $parent = $item->parentNode;

        while ($parent instanceof DOMElement) {
            $tag = strtolower($parent->tagName);

            if ($tag === 'ul' || $tag === 'ol') {
                $depth++;

                if ($depth === 1 && $tag === 'ol') {
                    $position = 1;
                    $sibling = $item->previousSibling;

                    while ($sibling !== null) {
                        if ($sibling instanceof DOMElement && strtolower($sibling->tagName) === 'li') {
                            $position++;
                        }

                        $sibling = $sibling->previousSibling;
                    }

                    $marker = $position.'.';
                }
            }

            $parent = $parent->parentNode;
        }

        return str_repeat('  ', max(0, $depth - 1)).$marker.' '.$this->inlineMarkdown($item);
    }

    private function quoteMarkdown(DOMNode $node): string
    {
        return implode(
            "\n",
            array_map(
                fn (string $line): string => '> '.$line,
                explode("\n", $this->inlineMarkdown($node)),
            ),
        );
    }

    private function inlineMarkdown(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $node->data;
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $inner = '';

        foreach ($node->childNodes as $child) {
            $inner .= $this->inlineMarkdown($child);
        }

        switch (strtolower($node->tagName)) {
            case 'b':
            case 'strong':
                return '**'.$inner.'**';
            case 'i':
            case 'em':
                return '*'.$inner.'*';
            case 's':
            case 'del':
                return '~~'.$inner.'~~';
            case 'code':
                return '`'.$inner.'`';
            case 'a':
                $href = $node->getAttribute('href');

                return $href === '' ? $inner : '['.$inner.']('.$href.')';
            case 'br':
                return "\n";
            case 'img':
                $src = $node->getAttribute('src');

                return $src === '' ? '' : '!['.$node->getAttribute('alt').']('.$src.')';
            default:
                return $inner;
        }
    }
}
