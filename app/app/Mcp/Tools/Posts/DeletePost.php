<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\RevisionSource;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Throwable;

#[Description('Delete a post. By default the post is archived (reversible: moved to trash, all revisions kept). A permanent delete requires permanent: true together with a matching if_version.')]
#[IsDestructive]
class DeletePost extends Tool
{
    use BuildsBlogV2Responses;

    public function __construct(
        protected PostService $posts,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:write', 'delete-post')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['required', 'integer'],
            'if_version' => ['nullable', 'integer', 'min:1'],
            'permanent' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        // withTrashed: a permanent delete usually follows an archive.
        $post = Post::withTrashed()->find($request->get('post_id'));

        if ($post === null) {
            return $this->notFound('post', (int) $request->get('post_id'));
        }

        $validated = $validator->validated();
        $ifVersion = isset($validated['if_version']) ? (int) $validated['if_version'] : null;

        try {
            if ($validated['permanent'] ?? false) {
                if ($ifVersion === null) {
                    return $this->invalidParam('if_version', 'a permanent delete requires a matching if_version.');
                }

                $postId = $post->id;
                $this->posts->deletePostPermanently($post, $ifVersion);

                return $this->payloadResponse([
                    'post_id' => $postId,
                    'deleted' => true,
                    'summary' => "Post {$postId} permanently deleted.",
                ]);
            }

            $post = $this->posts->archivePost(
                $post,
                $ifVersion,
                RevisionSource::Mcp,
                $this->clientName($request),
            );

            return $this->payloadResponse(
                $this->receipt($post, $this->latestRevision($post), "Post {$post->id} \"{$post->title}\" was archived (moved to trash)."),
            );
        } catch (Throwable $e) {
            return $this->mapServiceException($e) ?? throw $e;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
            'if_version' => $schema->integer()->description('Fail with version_conflict if the post changed since you read it. Required for permanent deletes.'),
            'permanent' => $schema->boolean()->description('Destroy the post and its revisions forever. Requires if_version. Default false: archive instead.'),
        ];
    }
}
