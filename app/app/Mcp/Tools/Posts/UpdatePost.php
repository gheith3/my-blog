<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
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

#[Description('Edit a post. Pass post_id plus only the fields to change (title, content, category_id, status, thumbnail, tag_ids, excerpt, slug, published_at). Omitted fields are left as they are. Returns a small receipt; set verbose for the full post. Use if_version to guard against overwriting a newer edit, and apply_to "pending" to stage title/content changes for the author.')]
class UpdatePost extends Tool
{
    use BuildsBlogV2Responses;

    /**
     * Fields PostService::updatePost() may write (metadata included).
     *
     * @var list<string>
     */
    private const UPDATABLE = ['title', 'content', 'category_id', 'status', 'thumbnail', 'excerpt', 'slug', 'published_at'];

    /**
     * apply_to "pending" stages only title and content (spec section 5); any
     * other field in the same call is rejected so nothing half-applies.
     *
     * @var list<string>
     */
    private const PENDING_FORBIDDEN = ['category_id', 'status', 'thumbnail', 'tag_ids', 'excerpt', 'slug', 'published_at'];

    public function __construct(
        protected PostService $posts,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:write', 'update-post')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['required', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:300'],
            'slug' => ['sometimes', 'string', 'max:80'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'if_version' => ['sometimes', 'integer', 'min:1'],
            'verbose' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'apply_to' => ['sometimes', 'in:live,pending'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return $this->notFound('post', (int) $request->get('post_id'));
        }

        $validated = $validator->validated();
        $ifVersion = isset($validated['if_version']) ? (int) $validated['if_version'] : null;
        $note = $validated['note'] ?? null;
        $clientName = $this->clientName($request);

        try {
            if (($validated['apply_to'] ?? 'live') === 'pending') {
                $extra = array_intersect(self::PENDING_FORBIDDEN, array_keys($validated));

                if ($extra !== []) {
                    return $this->invalidParam(
                        'apply_to',
                        'pending only stages title and content; remove '.implode(', ', $extra).' from this call.',
                    );
                }

                $revision = $this->posts->stagePendingUpdate(
                    $post,
                    Arr::only($validated, ['title', 'content']),
                    RevisionSource::Mcp,
                    $clientName,
                    $note,
                );

                return $this->payloadResponse(
                    $this->receipt($post->refresh(), $revision, 'Staged title/content change for author approval') + ['pending' => true],
                );
            }

            $changes = Arr::only($validated, self::UPDATABLE);

            $post = $this->posts->updatePost($post, $changes, RevisionSource::Mcp, $clientName, $note, $ifVersion);

            if (array_key_exists('tag_ids', $validated)) {
                $post->tags()->sync($validated['tag_ids']);
            }

            if ($validated['verbose'] ?? false) {
                return $this->payloadResponse(
                    $this->postPayload($post->refresh()) + ['revision_id' => $this->latestRevision($post)?->id],
                );
            }

            $changed = implode(', ', array_keys($changes));

            return $this->payloadResponse(
                $this->receipt($post, $this->latestRevision($post), 'Updated post'.($changed !== '' ? ": {$changed}" : '')),
            );
        } catch (Throwable $e) {
            return $this->mapServiceException($e, 'slug') ?? throw $e;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
            'title' => $schema->string(),
            'content' => $schema->string(),
            'category_id' => $schema->integer()->description('From list-categories.'),
            'status' => $schema->string()->description('draft, published, scheduled, or archived.'),
            'thumbnail' => $schema->string(),
            'tag_ids' => $schema->array()->items($schema->integer())->description('Replaces the post\'s tags with this list. Tag ids from list-tags.'),
            'excerpt' => $schema->string()->description('Listing card text and meta description, max 300 chars. Empty regenerates one from the first paragraph.'),
            'slug' => $schema->string()->description('URL path segment: lowercase latin letters, digits and hyphens, max 80. Renaming a published post keeps the old slug as a 301 redirect.'),
            'published_at' => $schema->string()->description('ISO 8601 publish date; a future date schedules the post.'),
            'if_version' => $schema->integer()->description('Fail with version_conflict if the post changed since you read it.'),
            'verbose' => $schema->boolean()->description('Return the full post instead of the lean receipt.'),
            'note' => $schema->string()->description('Stored on the revision, shown to the author. Max 200 chars.'),
            'apply_to' => $schema->string()->description('"live" (default) or "pending" to stage title/content changes for author approval.'),
        ];
    }
}
