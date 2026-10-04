<?php

namespace App\View\Components;

use App\Services\ContentViews;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Paragraph-level diff between two HTML bodies (Blog MCP v2, section 10):
 * blocks are aligned by index, changed paragraphs get a word-level character
 * highlight. Display-only, rendered with dir="rtl".
 */
class RevisionDiff extends Component
{
    /**
     * Token pairs above this size fall back to marking the whole paragraph as
     * changed, keeping the display diff cheap on very long paragraphs.
     */
    private const int MAX_TOKEN_PRODUCT = 20000;

    /**
     * Aligned blocks for the view.
     *
     * @var list<array{type: string, text: string, oldSegments: list<array{type: string, text: string}>, newSegments: list<array{type: string, text: string}>}>
     */
    public array $blocks;

    public function __construct(string $liveHtml, string $pendingHtml)
    {
        $views = app(ContentViews::class);

        $live = array_map(
            fn (array $block): string => $block['text'],
            $views->toParagraphs($liveHtml),
        );
        $pending = array_map(
            fn (array $block): string => $block['text'],
            $views->toParagraphs($pendingHtml),
        );

        $this->blocks = self::align($live, $pending);
    }

    public function render(): View|Closure|string
    {
        return view('components.revision-diff');
    }

    /**
     * Align two lists of paragraph texts by index.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{type: string, text: string, oldSegments: list<array{type: string, text: string}>, newSegments: list<array{type: string, text: string}>}>
     */
    public static function align(array $old, array $new): array
    {
        $blocks = [];
        $count = max(count($old), count($new));

        for ($i = 0; $i < $count; $i++) {
            $oldText = $old[$i] ?? null;
            $newText = $new[$i] ?? null;

            if ($oldText === null) {
                $blocks[] = ['type' => 'added', 'text' => $newText, 'oldSegments' => [], 'newSegments' => []];

                continue;
            }

            if ($newText === null) {
                $blocks[] = ['type' => 'removed', 'text' => $oldText, 'oldSegments' => [], 'newSegments' => []];

                continue;
            }

            if ($oldText === $newText) {
                $blocks[] = ['type' => 'unchanged', 'text' => $oldText, 'oldSegments' => [], 'newSegments' => []];

                continue;
            }

            [$oldSegments, $newSegments] = self::diffTokens($oldText, $newText);

            $blocks[] = [
                'type' => 'changed',
                'text' => '',
                'oldSegments' => $oldSegments,
                'newSegments' => $newSegments,
            ];
        }

        return $blocks;
    }

    /**
     * Word-level LCS diff between two plain texts. Returns segment lists for
     * the old and new side, each segment typed equal / removed / added.
     *
     * @return array{0: list<array{type: string, text: string}>, 1: list<array{type: string, text: string}>}
     */
    public static function diffTokens(string $old, string $new): array
    {
        $oldTokens = self::tokenize($old);
        $newTokens = self::tokenize($new);

        if (count($oldTokens) * count($newTokens) > self::MAX_TOKEN_PRODUCT) {
            return [
                [['type' => 'removed', 'text' => $old]],
                [['type' => 'added', 'text' => $new]],
            ];
        }

        $lcs = self::lcsTable($oldTokens, $newTokens);

        $oldSegments = [];
        $newSegments = [];
        $i = count($oldTokens);
        $j = count($newTokens);

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $oldTokens[$i - 1] === $newTokens[$j - 1]) {
                self::prependSegment($oldSegments, 'equal', $oldTokens[$i - 1]);
                self::prependSegment($newSegments, 'equal', $newTokens[$j - 1]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                self::prependSegment($newSegments, 'added', $newTokens[$j - 1]);
                $j--;
            } else {
                self::prependSegment($oldSegments, 'removed', $oldTokens[$i - 1]);
                $i--;
            }
        }

        return [$oldSegments, $newSegments];
    }

    /**
     * Split into words and whitespace runs, keeping the separators so the
     * paragraph can be reassembled exactly.
     *
     * @return list<string>
     */
    private static function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [$text] : $tokens;
    }

    /**
     * Classic LCS length table over token lists.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return array<int, array<int, int>>
     */
    private static function lcsTable(array $old, array $new): array
    {
        $table = array_fill(0, count($old) + 1, array_fill(0, count($new) + 1, 0));

        for ($i = 1; $i <= count($old); $i++) {
            for ($j = 1; $j <= count($new); $j++) {
                $table[$i][$j] = $old[$i - 1] === $new[$j - 1]
                    ? $table[$i - 1][$j - 1] + 1
                    : max($table[$i - 1][$j], $table[$i][$j - 1]);
            }
        }

        return $table;
    }

    /**
     * @param  list<array{type: string, text: string}>  $segments
     */
    private static function prependSegment(array &$segments, string $type, string $text): void
    {
        if ($segments !== [] && $segments[0]['type'] === $type) {
            $segments[0]['text'] = $text.$segments[0]['text'];

            return;
        }

        array_unshift($segments, ['type' => $type, 'text' => $text]);
    }
}
