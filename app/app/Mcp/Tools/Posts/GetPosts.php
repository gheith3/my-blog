<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Models\PostSlugRedirect;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read up to 20 posts in one call, for style reviews and cross-post work. Pick posts with post_ids or slugs, or omit both and filter by category_id/status. Format defaults to text; max_chars_per_post truncates each body and flags it with truncated: true.')]
#[IsReadOnly]
class GetPosts extends Tool
{
    use BuildsBlogV2Responses;

    /**
     * Max posts per call (Blog MCP v2, section 10).
     */
    private const int MAX_POSTS = 20;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'get-posts')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['integer'],
            'slugs' => ['nullable', 'array'],
            'slugs.*' => ['string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'format' => ['nullable', 'in:html,text,markdown,paragraphs'],
            'include_html' => ['nullable', 'boolean'],
            'max_chars_per_post' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $validated = $validator->validated();
        $postIds = $validated['post_ids'] ?? [];
        $slugs = $validated['slugs'] ?? [];

        if (count($postIds) + count($slugs) > self::MAX_POSTS) {
            return $this->payloadTooLarge('20 posts per call', count($postIds) + count($slugs));
        }

        $query = Post::query()->orderByDesc('id');

        if ($postIds !== [] || $slugs !== []) {
            $slugs = $this->resolveRedirectedSlugs($slugs);

            $query->where(fn ($q) => $q->whereIn('id', $postIds)->orWhereIn('slug', $slugs));
        } else {
            $query
                ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
                ->when($validated['category_id'] ?? null, fn ($q, int $categoryId) => $q->where('category_id', $categoryId))
                ->limit(self::MAX_POSTS);
        }

        $format = $validated['format'] ?? 'text';
        $includeHtml = (bool) ($validated['include_html'] ?? true);
        $maxChars = isset($validated['max_chars_per_post']) ? (int) $validated['max_chars_per_post'] : null;

        return $this->payloadResponse([
            'posts' => $query->get()
                ->map(fn (Post $post): array => $this->postPayload($post, $format, $includeHtml, $maxChars))
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_ids' => $schema->array()->items($schema->integer())->description('Which posts to read; max 20 across post_ids and slugs.'),
            'slugs' => $schema->array()->items($schema->string())->description('Which posts to read by slug; renamed slugs resolve through their redirect.'),
            'category_id' => $schema->integer()->description('Filter when no ids/slugs are given, from list-categories.'),
            'status' => $schema->string()->description('Filter when no ids/slugs are given: draft, published, scheduled, or archived.'),
            'format' => $schema->string()->description('text (default), html, markdown, or paragraphs.'),
            'include_html' => $schema->boolean()->description('For format paragraphs: set false to omit the per-block html field.'),
            'max_chars_per_post' => $schema->integer()->description('Truncate each body to this many characters, flagged with truncated: true.'),
        ];
    }

    /**
     * Swap renamed slugs for their current ones so redirects keep working
     * (spec section 7).
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function resolveRedirectedSlugs(array $slugs): array
    {
        $redirects = PostSlugRedirect::whereIn('old_slug', $slugs)->get();

        foreach ($redirects as $redirect) {
            $current = Post::withTrashed()->find($redirect->post_id)?->slug;

            if ($current !== null) {
                $slugs[] = $current;
            }
        }

        return array_values(array_unique($slugs));
    }
}
