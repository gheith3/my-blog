<?php

namespace App\Mcp\Tools\Revisions;

use App\Enums\RevisionSource;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Models\PostRevision;
use App\Services\PostService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Write a revision\'s snapshot back to the post. Creates a new applied revision; history is never deleted. Use if_version to guard against overwriting a newer edit.')]
class RestoreRevision extends Tool
{
    use BuildsBlogV2Responses;

    public function __construct(
        protected PostService $posts,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:write', 'restore-revision')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['required', 'integer'],
            'revision_id' => ['required', 'string'],
            'if_version' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return $this->notFound('post', (int) $request->get('post_id'));
        }

        $revision = PostRevision::find($request->get('revision_id'));

        if ($revision === null || $revision->post_id !== $post->id) {
            return $this->notFound('revision', (string) $request->get('revision_id'));
        }

        $validated = $validator->validated();

        try {
            $post = $this->posts->restoreRevision(
                $post,
                $revision,
                isset($validated['if_version']) ? (int) $validated['if_version'] : null,
                RevisionSource::Mcp,
                $this->clientName($request),
            );

            return $this->payloadResponse(
                $this->receipt($post, $this->latestRevision($post), "Restored revision {$revision->id}"),
            );
        } catch (Throwable $e) {
            return $this->mapServiceException($e) ?? throw $e;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
            'revision_id' => $schema->string()->required()->description('From list-revisions.'),
            'if_version' => $schema->integer()->description('Fail with version_conflict if the post changed since you read it.'),
        ];
    }
}
