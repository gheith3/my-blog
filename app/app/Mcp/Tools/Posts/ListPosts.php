<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
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

#[Description('List blog posts, newest first. Filter by status, category_id, or a title query. Paginate with cursor/next_cursor. Each row has id, title, slug, status, category, version, word_count, published_at and updated_at. Use the id with get-post, edit-post, update-post, and delete-post.')]
#[IsReadOnly]
class ListPosts extends Tool
{
    use BuildsBlogV2Responses;

    public function __construct(
        protected ContentViews $views,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'list-posts')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'query' => ['nullable', 'string', 'max:255'],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $validated = $validator->validated();
        $cursor = $validated['cursor'] ?? null;
        $afterId = $cursor === null ? null : $this->decodeCursor((string) $cursor);

        if ($cursor !== null && $afterId === null) {
            return $this->invalidParam('cursor', 'not a cursor returned by this tool.');
        }

        $limit = (int) ($validated['limit'] ?? 50);

        $posts = Post::query()
            ->with('category')
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['query'] ?? null, fn ($query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($afterId !== null, fn ($query) => $query->where('id', '<', $afterId))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit)->values();
        $last = $posts->last();

        return $this->payloadResponse([
            'posts' => $posts->map(fn (Post $post): array => [
                'post_id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'category_id' => $post->category_id,
                'category' => $post->category->name,
                'version' => $post->version,
                'word_count' => $this->views->wordCount($post->content),
                'published_at' => $post->published_at?->toIso8601String(),
                'updated_at' => $post->updated_at?->toIso8601String(),
            ])->all(),
            'next_cursor' => $hasMore && $last !== null ? base64_encode((string) $last->id) : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Optional filter: draft, published, scheduled, or archived.'),
            'category_id' => $schema->integer()->description('Optional filter, from list-categories.'),
            'query' => $schema->string()->description('Optional title filter.'),
            'cursor' => $schema->string()->description('Pass the next_cursor of the previous page to continue.'),
            'limit' => $schema->integer()->description('Posts per call, default 50, max 100.'),
        ];
    }

    /**
     * A cursor is the base64-encoded id of the last row of the previous page.
     */
    private function decodeCursor(string $cursor): ?int
    {
        $decoded = base64_decode($cursor, true);

        if ($decoded === false || ! ctype_digit($decoded)) {
            return null;
        }

        return (int) $decoded;
    }
}
