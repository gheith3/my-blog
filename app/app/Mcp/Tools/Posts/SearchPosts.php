<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
use App\Mcp\Support\ArabicSearchMatcher;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Services\ContentViews;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search post titles and bodies for words or a phrase. Arabic is normalized for matching only (harakat and tatweel ignored; أ إ آ ٱ count as ا, ى as ي, ة as ه), so "اخطاء" finds "أخطاء". Stored text and snippets are never normalized. Returns match_count and up to 3 snippets per post.')]
#[IsReadOnly]
class SearchPosts extends Tool
{
    use BuildsBlogV2Responses;

    /**
     * Candidates scanned per call. There is no normalized SQL column, so
     * matching runs in PHP over decoded text: the filtered, most recent
     * candidates are fetched and matched in memory.
     */
    private const int CANDIDATE_CAP = 500;

    /**
     * Approximate snippet length in characters.
     */
    private const int SNIPPET_LENGTH = 120;

    public function __construct(
        protected ContentViews $views,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'search-posts')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'min:1', 'max:255'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $validated = $validator->validated();
        $query = trim((string) $validated['query']);

        if (ArabicSearchMatcher::normalize($query) === '') {
            return $this->invalidParam('query', 'the query has no searchable characters.');
        }

        $candidates = Post::query()
            ->when($validated['status'] ?? null, fn ($queryBuilder, string $status) => $queryBuilder->where('status', $status))
            ->when($validated['category_id'] ?? null, fn ($queryBuilder, int $categoryId) => $queryBuilder->where('category_id', $categoryId))
            ->orderByDesc('id')
            ->limit(self::CANDIDATE_CAP)
            ->get();

        $results = [];

        foreach ($candidates as $post) {
            $titleMatches = ArabicSearchMatcher::find($post->title, $query);
            $body = $this->views->toText($post->content);
            $bodyMatches = ArabicSearchMatcher::find($body, $query);

            if ($titleMatches === [] && $bodyMatches === []) {
                continue;
            }

            $snippets = array_merge(
                array_map(fn (array $match): string => $this->snippet($post->title, $match), array_slice($titleMatches, 0, 3)),
                array_map(fn (array $match): string => $this->snippet($body, $match), array_slice($bodyMatches, 0, 3)),
            );

            $results[] = [
                'post_id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'match_count' => count($titleMatches) + count($bodyMatches),
                'snippets' => array_slice($snippets, 0, 3),
            ];
        }

        usort($results, fn (array $a, array $b): int => $b['match_count'] <=> $a['match_count'] ?: $b['post_id'] <=> $a['post_id']);

        return $this->payloadResponse([
            'query' => $query,
            'results' => array_slice($results, 0, (int) ($validated['limit'] ?? 20)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Words or a phrase to find in titles and bodies.'),
            'status' => $schema->string()->description('Optional filter: draft, published, scheduled, or archived.'),
            'category_id' => $schema->integer()->description('Optional filter, from list-categories.'),
            'limit' => $schema->integer()->description('Max results, default 20, max 100.'),
        ];
    }

    /**
     * About 120 characters of the ORIGINAL text around a match.
     *
     * @param  array{start: int, end: int}  $match
     */
    private function snippet(string $text, array $match): string
    {
        $context = (int) ((self::SNIPPET_LENGTH - ($match['end'] - $match['start'])) / 2);
        $context = max(20, $context);

        $start = max(0, $match['start'] - $context);
        $length = min(mb_strlen($text), $match['end'] + $context) - $start;

        $snippet = str_replace("\n", ' ', mb_substr($text, $start, $length));

        return ($start > 0 ? '…' : '').$snippet.($start + $length < mb_strlen($text) ? '…' : '');
    }
}
