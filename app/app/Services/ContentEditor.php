<?php

namespace App\Services;

use App\Exceptions\EditFailedException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use DOMXPath;
use InvalidArgumentException;

/**
 * The edit-post engine from Blog MCP v2 section 4: applies a list of exact
 * find-and-replace edits to stored post HTML, atomically.
 *
 * Matching runs on decoded block text with exact code-point comparison: no
 * case folding, no whitespace collapsing, no normalization, harakat count as
 * characters. Every edit is located in the original text before any edit is
 * applied, replacements never inject markup, and block attributes survive.
 */
final class ContentEditor
{
    /** @var list<string> Tags that form one block each in document order. Must match ContentViews. */
    private const array BLOCK_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'figcaption', 'pre', 'td', 'th',
    ];

    /** Characters of context shown on each side of a changed span in dry-run diffs. */
    private const int CONTEXT_LENGTH = 40;

    /** Characters stripped when hunting for near matches: harakat and invisibles. */
    private const string STRIPPABLE_PATTERN = '/[\x{064B}-\x{065F}\x{0670}\x{200E}\x{200F}\x{200C}\x{200D}\x{0640}\x{00A0}]/u';

    /** Characters that continue a word for whole_word matching: letters, combining marks, tatweel. */
    private const string WORD_CHARACTER_PATTERN = '/[\p{L}\p{M}\x{0640}]/u';

    /**
     * Validate and apply every edit atomically.
     *
     * @param  list<array{old: string, new: string, occurrence?: int|null, paragraph?: int|null, whole_word?: bool|null}>  $edits
     *
     * @throws InvalidArgumentException On a structurally invalid edit (missing or empty `old`, missing `new`).
     * @throws EditFailedException When any edit cannot be applied; nothing is changed.
     */
    public function apply(string $html, array $edits, bool $wholeWordDefault = false): string
    {
        if ($edits === []) {
            return $html;
        }

        [$document, , $located] = $this->locate($html, $edits, $wholeWordDefault);

        $byBlock = [];

        foreach ($located as $editIndex => $match) {
            $byBlock[$match['block']][$editIndex] = $match;
        }

        foreach ($byBlock as $blockMatches) {
            // Replace from the end of the block backwards so earlier byte
            // offsets stay valid while text nodes change length.
            uasort($blockMatches, fn (array $a, array $b): int => $b['start'] <=> $a['start']);

            foreach ($blockMatches as $editIndex => $match) {
                $node = $match['node'];
                $node->data = substr_replace(
                    $node->data,
                    $edits[$editIndex]['new'],
                    $match['start'] - $match['nodeStart'],
                    $match['end'] - $match['start'],
                );
            }
        }

        return $this->serialize($document);
    }

    /**
     * Validate the edits and return a per-edit diff without producing HTML.
     *
     * @param  list<array{old: string, new: string, occurrence?: int|null, paragraph?: int|null, whole_word?: bool|null}>  $edits
     * @return list<array{index: int, paragraph: int, before: string, after: string}>
     *                                                                                `index` is the edit's 0-based position in the edits array; `before`
     *                                                                                and `after` show the changed span with about 40 characters of
     *                                                                                context on each side.
     *
     * @throws InvalidArgumentException On a structurally invalid edit.
     * @throws EditFailedException When any edit cannot be applied.
     */
    public function validate(string $html, array $edits, bool $wholeWordDefault = false): array
    {
        if ($edits === []) {
            return [];
        }

        [, $blocks, $located] = $this->locate($html, $edits, $wholeWordDefault);

        $diff = [];

        foreach ($located as $editIndex => $match) {
            $text = $blocks[$match['block']]['text'];
            $span = $this->diffContext($text, $match['start'], $match['end'], $edits[$editIndex]['new']);

            $diff[] = [
                'index' => $editIndex,
                'paragraph' => $match['block'] + 1,
                'before' => $span['before'],
                'after' => $span['after'],
            ];
        }

        return $diff;
    }

    /**
     * Locate every edit in the original text and check the failure modes.
     *
     * @param  list<array{old: string, new: string, occurrence?: int|null, paragraph?: int|null, whole_word?: bool|null}>  $edits
     * @return array{0: DOMDocument, 1: list<array{type: string, text: string, node: DOMNode, textNodes: list<array{node: DOMText, start: int, end: int}>}>, 2: array<int, array{block: int, start: int, end: int, node: DOMText, nodeStart: int}>}
     *
     * @throws EditFailedException
     */
    private function locate(string $html, array $edits, bool $wholeWordDefault): array
    {
        $this->validateEditStructures($edits);

        $document = $this->parse($html);
        $blocks = $this->blocks($document);
        $failures = [];
        $located = [];

        foreach ($edits as $index => $edit) {
            $wholeWord = $edit['whole_word'] ?? $wholeWordDefault;
            $scope = $this->scopeIndexes(count($blocks), $edit['paragraph'] ?? null);

            $matches = [];

            foreach ($scope as $blockIndex) {
                foreach ($this->findInText($blocks[$blockIndex]['text'], $edit['old'], $wholeWord) as $offset) {
                    $matches[] = ['block' => $blockIndex, 'start' => $offset];
                }
            }

            $occurrence = $edit['occurrence'] ?? null;

            if ($occurrence !== null) {
                $matches = isset($matches[$occurrence - 1]) ? [$matches[$occurrence - 1]] : [];
            }

            if ($matches === []) {
                $failures[$index] = [
                    'index' => $index,
                    'code' => 'edit_not_found',
                    'old' => $edit['old'],
                    'near_matches' => $this->nearMatches($blocks, $scope, $edit['old']),
                ];

                continue;
            }

            if (count($matches) > 1) {
                $failures[$index] = [
                    'index' => $index,
                    'code' => 'edit_ambiguous',
                    'old' => $edit['old'],
                    'count' => count($matches),
                    'paragraphs' => array_values(array_unique(array_map(
                        fn (array $match): int => $match['block'] + 1,
                        $matches,
                    ))),
                ];

                continue;
            }

            $match = $matches[0];
            $match['end'] = $match['start'] + strlen($edit['old']);
            $nodeMatch = $this->resolveTextNode($blocks[$match['block']], $match['start'], $match['end']);

            if ($nodeMatch === null) {
                $failures[$index] = [
                    'index' => $index,
                    'code' => 'edit_spans_markup',
                    'old' => $edit['old'],
                    'paragraph' => $match['block'] + 1,
                ];

                continue;
            }

            $located[$index] = $match + $nodeMatch;
        }

        foreach ($this->overlaps($located) as $index => $cluster) {
            $failures[$index] = [
                'index' => $index,
                'code' => 'edit_overlap',
                'old' => $edits[$index]['old'],
                'indexes' => $cluster,
            ];
        }

        if ($failures !== []) {
            ksort($failures);

            throw new EditFailedException(array_values($failures), count($edits));
        }

        return [$document, $blocks, $located];
    }

    /**
     * @param  list<array{old: string, new: string, occurrence?: int|null, paragraph?: int|null, whole_word?: bool|null}>  $edits
     */
    private function validateEditStructures(array $edits): void
    {
        foreach ($edits as $index => $edit) {
            if (! is_array($edit)) {
                throw new InvalidArgumentException("edits[$index] must be an array.");
            }

            if (! isset($edit['old']) || ! is_string($edit['old']) || $edit['old'] === '') {
                throw new InvalidArgumentException("edits[$index].old must be a non-empty string.");
            }

            if (! array_key_exists('new', $edit) || ! is_string($edit['new'])) {
                throw new InvalidArgumentException("edits[$index].new must be a string.");
            }

            if (isset($edit['occurrence']) && (! is_int($edit['occurrence']) || $edit['occurrence'] < 1)) {
                throw new InvalidArgumentException("edits[$index].occurrence must be a 1-based integer.");
            }

            if (isset($edit['paragraph']) && (! is_int($edit['paragraph']) || $edit['paragraph'] < 1)) {
                throw new InvalidArgumentException("edits[$index].paragraph must be a 1-based integer.");
            }

            if (isset($edit['whole_word']) && ! is_bool($edit['whole_word'])) {
                throw new InvalidArgumentException("edits[$index].whole_word must be a boolean.");
            }
        }
    }

    /**
     * @return list<int> Block indexes (0-based) the edit may match in.
     */
    private function scopeIndexes(int $blockCount, ?int $paragraph): array
    {
        if ($paragraph === null) {
            return range(0, max(0, $blockCount - 1));
        }

        return $paragraph <= $blockCount ? [$paragraph - 1] : [];
    }

    /**
     * All byte offsets of `old` in `text`, honouring whole-word boundaries.
     *
     * @return list<int>
     */
    private function findInText(string $text, string $old, bool $wholeWord): array
    {
        $offsets = [];
        $position = 0;
        $length = strlen($old);

        while (($found = strpos($text, $old, $position)) !== false) {
            if (! $wholeWord || $this->isWholeWordMatch($text, $found, $length)) {
                $offsets[] = $found;
            }

            $position = $found + 1;
        }

        return $offsets;
    }

    /**
     * A whole-word match must not be preceded or followed by a letter, an
     * Arabic combining mark (U+064B–U+065F, U+0670) or tatweel (U+0640), so
     * `مباغتا` does not match inside `مباغتاً`.
     */
    private function isWholeWordMatch(string $text, int $start, int $length): bool
    {
        return ! $this->isWordCharacter($this->characterBefore($text, $start))
            && ! $this->isWordCharacter($this->characterAt($text, $start + $length));
    }

    private function characterBefore(string $text, int $byteOffset): ?string
    {
        if ($byteOffset <= 0) {
            return null;
        }

        return mb_substr(substr($text, 0, $byteOffset), -1);
    }

    private function characterAt(string $text, int $byteOffset): ?string
    {
        if ($byteOffset >= strlen($text)) {
            return null;
        }

        return mb_substr(substr($text, $byteOffset), 0, 1);
    }

    private function isWordCharacter(?string $character): bool
    {
        return $character !== null && preg_match(self::WORD_CHARACTER_PATTERN, $character) === 1;
    }

    /**
     * Map a byte span inside a block to the single text node holding it, or
     * null when the span crosses text nodes (inline formatting).
     *
     * @param  array{textNodes: list<array{node: DOMText, start: int, end: int}>}  $block
     * @return array{node: DOMText, nodeStart: int}|null
     */
    private function resolveTextNode(array $block, int $start, int $end): ?array
    {
        foreach ($block['textNodes'] as $info) {
            if ($info['start'] <= $start && $end <= $info['end']) {
                return ['node' => $info['node'], 'nodeStart' => $info['start']];
            }

            if ($info['start'] <= $start && $start < $info['end']) {
                return null;
            }
        }

        return null;
    }

    /**
     * Groups of located edits whose byte spans overlap inside one block.
     *
     * @param  array<int, array{block: int, start: int, end: int}>  $located
     * @return array<int, list<int>> Overlap cluster per failing edit index.
     */
    private function overlaps(array $located): array
    {
        $byBlock = [];

        foreach ($located as $index => $match) {
            $byBlock[$match['block']][$index] = $match;
        }

        $clusters = [];

        foreach ($byBlock as $blockMatches) {
            uasort($blockMatches, fn (array $a, array $b): int => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);

            $maxEnd = -1;
            $maxIndex = null;

            foreach ($blockMatches as $index => $match) {
                if ($match['start'] < $maxEnd) {
                    $clusters[$maxIndex][] = $index;
                    $clusters[$index][] = $maxIndex;
                }

                if ($match['end'] > $maxEnd) {
                    $maxEnd = $match['end'];
                    $maxIndex = $index;
                }
            }
        }

        foreach ($clusters as $index => $others) {
            $cluster = array_values(array_unique(array_merge([$index], $others)));
            sort($cluster);
            $clusters[$index] = $cluster;
        }

        return $clusters;
    }

    /**
     * Up to 3 candidates that differ from `old` only by harakat, invisible
     * characters or whitespace.
     *
     * @param  list<array{text: string}>  $blocks
     * @param  list<int>  $scope
     * @return list<array{paragraph: int, text: string, difference: string}>
     */
    private function nearMatches(array $blocks, array $scope, string $old): array
    {
        $target = $this->normalizeForComparison($old);

        if ($target === '') {
            return [];
        }

        $results = [];

        foreach ($scope as $blockIndex) {
            if (count($results) >= 3) {
                break;
            }

            $candidate = $this->findNearMatch($blocks[$blockIndex]['text'], $target);

            if ($candidate !== null) {
                $results[] = [
                    'paragraph' => $blockIndex + 1,
                    'text' => $candidate,
                    'difference' => $this->describeDifference($old, $candidate),
                ];
            }
        }

        return $results;
    }

    /**
     * Find the substring of $text whose stripped form equals the stripped
     * target, using a position map from normalized characters back to the
     * original characters.
     */
    private function findNearMatch(string $text, string $target): ?string
    {
        $characters = mb_str_split($text);
        $normalized = '';
        $positions = [];
        $pendingSpace = false;

        foreach ($characters as $offset => $character) {
            if (preg_match(self::STRIPPABLE_PATTERN, $character) === 1) {
                continue;
            }

            if (preg_match('/\s/u', $character) === 1) {
                $pendingSpace = $normalized !== '';

                continue;
            }

            if ($pendingSpace) {
                $normalized .= ' ';
                $positions[] = $offset;
                $pendingSpace = false;
            }

            $normalized .= $character;
            $positions[] = $offset;
        }

        $targetLength = mb_strlen($target);
        $found = mb_strpos($normalized, $target);

        if ($found === false || $targetLength === 0) {
            return null;
        }

        $first = $positions[$found];
        $last = $positions[$found + $targetLength - 1];

        return implode('', array_slice($characters, $first, $last - $first + 1));
    }

    /**
     * Strip harakat, invisible characters and tatweel, collapse whitespace.
     */
    private function normalizeForComparison(string $text): string
    {
        $normalized = preg_replace(self::STRIPPABLE_PATTERN, '', $text) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    /**
     * Name the characters that differ between `old` and the near-match
     * candidate, e.g. "invisible character U+200F".
     */
    private function describeDifference(string $old, string $candidate): string
    {
        $strippedOld = array_count_values($this->strippedCharacters($old));
        $strippedCandidate = array_count_values($this->strippedCharacters($candidate));

        $names = [];

        foreach ([$strippedCandidate, $strippedOld] as $position => $side) {
            $other = $position === 0 ? $strippedOld : $strippedCandidate;

            foreach ($side as $character => $count) {
                if ($count > ($other[$character] ?? 0)) {
                    $names[] = $this->describeCharacter($character);
                }
            }
        }

        $names = array_values(array_unique($names));

        return $names === [] ? 'whitespace' : implode(', ', $names);
    }

    /**
     * @return list<string>
     */
    private function strippedCharacters(string $text): array
    {
        preg_match_all(self::STRIPPABLE_PATTERN, $text, $matches);

        return $matches[0];
    }

    private function describeCharacter(string $character): string
    {
        $code = mb_ord($character);

        return match (true) {
            $code === 0x200E, $code === 0x200F, $code === 0x200C, $code === 0x200D => sprintf('invisible character U+%04X', $code),
            $code === 0x0640 => 'tatweel U+0640',
            $code === 0x00A0 => 'no-break space U+00A0',
            default => sprintf('haraka U+%04X', $code),
        };
    }

    /**
     * The changed span with about 40 characters of context on each side.
     *
     * @return array{before: string, after: string}
     */
    private function diffContext(string $text, int $byteStart, int $byteEnd, string $new): array
    {
        $charStart = mb_strlen(substr($text, 0, $byteStart));
        $charLength = mb_strlen(substr($text, $byteStart, $byteEnd - $byteStart));
        $totalLength = mb_strlen($text);

        $windowStart = max(0, $charStart - self::CONTEXT_LENGTH);
        $windowEnd = min($totalLength, $charStart + $charLength + self::CONTEXT_LENGTH);

        $prefix = $windowStart > 0 ? '...' : '';
        $suffix = $windowEnd < $totalLength ? '...' : '';

        $before = $prefix.mb_substr($text, $windowStart, $windowEnd - $windowStart).$suffix;
        $after = $prefix
            .mb_substr($text, $windowStart, $charStart - $windowStart)
            .$new
            .mb_substr($text, $charStart + $charLength, $windowEnd - $charStart - $charLength)
            .$suffix;

        return ['before' => $before, 'after' => $after];
    }

    /**
     * Parse the fragment and enumerate its top-level blocks with a map from
     * each text offset to its text node.
     *
     * @return list<array{type: string, text: string, node: DOMNode, textNodes: list<array{node: DOMText, start: int, end: int}>}>
     */
    private function blocks(DOMDocument $document): array
    {
        $blocks = [];
        $this->collectBlocks($document, $blocks);

        foreach ($blocks as $position => $block) {
            $blocks[$position]['textNodes'] = $this->textNodeMap($block['node']);
        }

        return $blocks;
    }

    /**
     * @param  list<array{type: string, text: string, node: DOMNode}>  $blocks
     */
    private function collectBlocks(DOMNode $node, array &$blocks): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->data) !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $child->data, 'node' => $child];
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::BLOCK_TAGS, true)) {
                $blocks[] = ['type' => $tag, 'text' => $child->textContent, 'node' => $child];

                continue;
            }

            if ($this->hasBlockDescendant($child)) {
                $this->collectBlocks($child, $blocks);

                continue;
            }

            if (trim($child->textContent) !== '') {
                $blocks[] = ['type' => $tag, 'text' => $child->textContent, 'node' => $child];
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
     * Every text node inside a block with its byte range in the block text.
     *
     * @return list<array{node: DOMText, start: int, end: int}>
     */
    private function textNodeMap(DOMNode $node): array
    {
        if ($node instanceof DOMText) {
            return [['node' => $node, 'start' => 0, 'end' => strlen($node->data)]];
        }

        $map = [];
        $offset = 0;

        $walk = function (DOMNode $current) use (&$walk, &$map, &$offset): void {
            foreach ($current->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $length = strlen($child->data);
                    $map[] = ['node' => $child, 'start' => $offset, 'end' => $offset + $length];
                    $offset += $length;
                } else {
                    $walk($child);
                }
            }
        };

        $walk($node);

        return $map;
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
     * Serialize back to HTML in the entity style the editor uses today (`"`
     * becomes `&quot;`, U+00A0 becomes `&nbsp;` in text), so untouched blocks
     * stay byte-identical and `new` can never inject markup: libxml escapes
     * `<` and `&` in text nodes on its own.
     */
    private function serialize(DOMDocument $document): string
    {
        $text = $document->textContent;
        $quotePlaceholder = $this->freePlaceholder($text);
        $nbspPlaceholder = $this->freePlaceholder($text.$quotePlaceholder);

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

        $html = '';

        foreach ($document->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return str_replace(
            [$quotePlaceholder, $nbspPlaceholder],
            ['&quot;', '&nbsp;'],
            $html,
        );
    }

    /**
     * A private-use character guaranteed absent from the text, so it can
     * stand in for a special character during serialization.
     */
    private function freePlaceholder(string $text): string
    {
        for ($code = 0xE000; $code <= 0xF8FE; $code++) {
            if (! str_contains($text, mb_chr($code))) {
                return mb_chr($code);
            }
        }

        return "\u{F8FF}";
    }
}
