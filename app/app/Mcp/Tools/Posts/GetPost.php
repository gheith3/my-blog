<?php

namespace App\Mcp\Tools\Posts;

use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Models\PostSlugRedirect;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one post by post_id, slug, or public url (exactly one). Renamed slugs resolve through their redirect. Choose format html (default), text, markdown, or paragraphs; the text view is exactly what edit-post matches against. Returns version, excerpt, word count, reading time, dates and URL alongside the content.')]
#[IsReadOnly]
class GetPost extends Tool
{
    use BuildsBlogV2Responses;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'get-post')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'url'],
            'format' => ['nullable', 'in:html,text,markdown,paragraphs'],
            'include_html' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $validated = $validator->validated();

        $locators = array_filter([
            'post_id' => $validated['post_id'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'url' => $validated['url'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if (count($locators) !== 1) {
            return $this->invalidParam('post_id', 'pass exactly one of post_id, slug or url.');
        }

        $locator = array_key_first($locators);
        $value = $locators[$locator];

        if ($locator === 'url') {
            $value = $this->slugFromUrl((string) $value);

            if ($value === null) {
                return $this->invalidParam('url', 'the URL does not look like a post URL (…/posts/{slug}).');
            }

            $locator = 'slug';
        }

        $post = $locator === 'post_id'
            ? Post::find($value)
            : $this->findBySlug((string) $value);

        if ($post === null) {
            return $this->notFound($locator === 'post_id' ? 'post' : 'slug', $value);
        }

        return $this->payloadResponse($this->postPayload(
            $post,
            $validated['format'] ?? 'html',
            (bool) ($validated['include_html'] ?? true),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('The post id, from list-posts. Pass exactly one of post_id, slug, url.'),
            'slug' => $schema->string()->description('The post slug; renamed slugs resolve through their redirect.'),
            'url' => $schema->string()->description('A full public post URL, e.g. https://gheith.me/posts/q3nbwPmo.'),
            'format' => $schema->string()->description('html (default), text, markdown, or paragraphs.'),
            'include_html' => $schema->boolean()->description('For format paragraphs: set false to omit the per-block html field.'),
        ];
    }

    /**
     * Extract the slug from a public post URL like
     * https://gheith.me/posts/q3nbwPmo.
     */
    private function slugFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('~/posts/(?<slug>[^/?#]+)/?$~', $path, $matches) === 1
            ? $matches['slug']
            : null;
    }

    /**
     * Find a post by slug, following a permanent redirect when the slug was
     * renamed (spec section 7).
     */
    private function findBySlug(string $slug): ?Post
    {
        $post = Post::where('slug', $slug)->first();

        if ($post !== null) {
            return $post;
        }

        $redirect = PostSlugRedirect::where('old_slug', $slug)->first();

        return $redirect === null ? null : Post::withTrashed()->find($redirect->post_id);
    }
}
