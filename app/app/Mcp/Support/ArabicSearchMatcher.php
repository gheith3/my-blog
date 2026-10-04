<?php

namespace App\Mcp\Support;

/**
 * Arabic-normalizing matcher for search-posts (Blog MCP v2, section 7).
 *
 * Normalization is for MATCHING ONLY — stored text and returned snippets are
 * never normalized. It strips harakat (U+064B–U+065F, U+0670) and tatweel
 * (U+0640), folds أ إ آ ٱ to ا, ى to ي, and ة to ه, so "اخطاء" finds "أخطاء".
 * A position map tracks every normalized character back to its offset in the
 * original text, so snippets are cut from the untouched original.
 */
final class ArabicSearchMatcher
{
    private const string STRIPPED_PATTERN = '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u';

    /**
     * All matches of $query in $text as original-text character spans.
     *
     * @return list<array{start: int, end: int}> Character offsets into $text.
     */
    public static function find(string $text, string $query): array
    {
        [$normalizedText, $textMap] = self::normalizeWithMap($text);
        [$normalizedQuery] = self::normalizeWithMap($query);

        if ($normalizedQuery === '' || $normalizedText === '') {
            return [];
        }

        $matches = [];
        $position = 0;
        $queryLength = mb_strlen($normalizedQuery);

        while (($found = mb_strpos($normalizedText, $normalizedQuery, $position)) !== false) {
            $matches[] = [
                'start' => $textMap[$found],
                'end' => $textMap[$found + $queryLength - 1] + 1,
            ];
            $position = $found + 1;
        }

        return $matches;
    }

    /**
     * Normalize for comparison: strip harakat and tatweel, fold hamza/ya/ta
     * variants, collapse whitespace. Never use the result for display.
     */
    public static function normalize(string $text): string
    {
        return self::normalizeWithMap($text)[0];
    }

    /**
     * @return array{0: string, 1: list<int>} The normalized string and, per
     *                                        normalized character, its character offset in the original.
     */
    private static function normalizeWithMap(string $text): array
    {
        $normalized = '';
        $map = [];
        $pendingSpace = false;

        foreach (mb_str_split($text) as $offset => $character) {
            if (preg_match(self::STRIPPED_PATTERN, $character) === 1) {
                continue;
            }

            if (preg_match('/\s/u', $character) === 1) {
                $pendingSpace = $normalized !== '';

                continue;
            }

            if ($pendingSpace) {
                $normalized .= ' ';
                $map[] = $offset;
                $pendingSpace = false;
            }

            $normalized .= strtr($character, [
                'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
                'ى' => 'ي', 'ة' => 'ه',
            ]);
            $map[] = $offset;
        }

        return [$normalized, $map];
    }
}
