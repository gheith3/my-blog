<?php

namespace App\Mcp\Tools\Comments;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Mcp\Tools\Concerns\DescribesBlogRecords;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List comments, newest first. Filter by post_id and/or moderation state (approved or pending). Use it to find comments awaiting approval. Returns each comment\'s id, post, author, and state.')]
#[IsReadOnly]
class ListComments extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'comments:read')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'state' => ['nullable', 'in:approved,pending'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first());
        }

        $comments = Comment::query()
            ->when($request->get('post_id'), fn ($query, int $postId) => $query
                ->where('commentable_type', Post::class)
                ->where('commentable_id', $postId))
            ->when($request->get('state') === 'approved', fn ($query) => $query->where('is_approved', true))
            ->when($request->get('state') === 'pending', fn ($query) => $query->where('is_approved', false))
            ->latest()
            ->limit((int) $request->get('limit', 50))
            ->get();

        if ($comments->isEmpty()) {
            return Response::text('No comments found.');
        }

        return Response::text($comments->map(fn (Comment $comment): string => $this->describeComment($comment))->implode("\n"));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('Only comments on this post.'),
            'state' => $schema->string()->description('approved or pending.'),
            'limit' => $schema->integer()->description('Comments per call, default 50, max 100.'),
        ];
    }
}
