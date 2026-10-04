<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Services\PostService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Create a blog post. Requires a title, content, and category_id (from list-categories). Defaults to draft; set status to published to make it live, or published_at in the future to schedule it. Returns a small receipt with the new post id and slug; set verbose for the full post.')]
class CreatePost extends Tool
{
    use BuildsBlogV2Responses;

    public function __construct(
        protected PostService $posts,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:write', 'create-post')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'slug' => ['nullable', 'string', 'max:80'],
            'published_at' => ['nullable', 'date'],
            'verbose' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $validated = $validator->validated();

        try {
            $post = $this->posts->createPost(
                Arr::only($validated, ['title', 'content', 'category_id', 'status', 'thumbnail', 'excerpt', 'slug', 'published_at'])
                    + ['user_id' => $request->user()->id],
                RevisionSource::Mcp,
                $this->clientName($request),
                $validated['note'] ?? null,
            );

            $post->tags()->sync($validated['tag_ids'] ?? []);

            if ($validated['verbose'] ?? false) {
                return $this->payloadResponse(
                    $this->postPayload($post->refresh()) + ['revision_id' => $this->latestRevision($post)?->id],
                );
            }

            return $this->payloadResponse(
                $this->receipt($post, $this->latestRevision($post), "Post created as {$post->status->value} with id {$post->id}"),
            );
        } catch (Throwable $e) {
            return $this->mapServiceException($e, 'slug') ?? throw $e;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'content' => $schema->string()->required()->description('Full post body.'),
            'category_id' => $schema->integer()->required()->description('From list-categories.'),
            'status' => $schema->string()->description('draft (default), published, scheduled, or archived.'),
            'thumbnail' => $schema->string()->description('Image URL or path, optional.'),
            'tag_ids' => $schema->array()->items($schema->integer())->description('Tag ids from list-tags.'),
            'excerpt' => $schema->string()->description('Listing card text and meta description, max 300 chars. Omit to generate one from the first paragraph.'),
            'slug' => $schema->string()->description('URL path segment: lowercase latin letters, digits and hyphens, max 80. Omit for a random slug.'),
            'published_at' => $schema->string()->description('ISO 8601 publish date; a future date schedules the post.'),
            'verbose' => $schema->boolean()->description('Return the full post instead of the lean receipt.'),
            'note' => $schema->string()->description('Stored on the revision, shown to the author. Max 200 chars.'),
        ];
    }
}
