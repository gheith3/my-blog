<?php

namespace App\Mcp\Tools\Revisions;

use App\Enums\RevisionState;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Models\PostRevision;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the revisions of a post, newest first, without bodies. Every saved change is a revision; state "pending" means staged for author approval. Use get-revision for a snapshot or diff.')]
#[IsReadOnly]
class ListRevisions extends Tool
{
    use BuildsBlogV2Responses;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'list-revisions')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['required', 'integer'],
            'state' => ['nullable', Rule::enum(RevisionState::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return $this->notFound('post', (int) $request->get('post_id'));
        }

        $revisions = $post->revisions()
            ->when($request->get('state'), fn ($query, string $state) => $query->where('state', $state))
            ->orderByDesc('id')
            ->limit((int) $request->get('limit', 20))
            ->get();

        return $this->payloadResponse([
            'post_id' => $post->id,
            'version' => $post->version,
            'revisions' => $revisions->map(fn (PostRevision $revision): array => [
                'revision_id' => $revision->id,
                'version' => $revision->version,
                'base_version' => $revision->base_version,
                'state' => $revision->state->value,
                'source' => $revision->source->value,
                'client_name' => $revision->client_name,
                'note' => $revision->note,
                'unguarded' => $revision->unguarded,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
            'state' => $schema->string()->description('Optional filter: applied, pending, rejected, conflict, or superseded.'),
            'limit' => $schema->integer()->description('Revisions per call, default 20, max 100.'),
        ];
    }
}
