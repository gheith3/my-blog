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

#[Description('Add a comment to a post, or a reply to an existing comment on that post (pass parent_id). Comments start pending unless is_approved is true, so they stay hidden until approved.')]
class CreateComment extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'comments:write')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first());
        }

        $validated = $validator->validated();
        $post = Post::findOrFail($validated['post_id']);

        if (isset($validated['parent_id']) && ! $this->isCommentOnPost($validated['parent_id'], $post->id)) {
            return Response::error('parent_id must be a comment on the same post.');
        }

        $comment = $post->comments()->create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'content' => $validated['content'],
            'is_approved' => $validated['is_approved'] ?? false,
        ]);

        return Response::text("Comment created with id {$comment->id}.\n".$this->describeComment($comment));
    }

    protected function isCommentOnPost(int $commentId, int $postId): bool
    {
        return Comment::query()
            ->whereKey($commentId)
            ->where('commentable_type', Post::class)
            ->where('commentable_id', $postId)
            ->exists();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post to comment on, from list-posts.'),
            'name' => $schema->string()->required()->description('Author display name.'),
            'content' => $schema->string()->required(),
            'parent_id' => $schema->integer()->description('Comment id to reply to. Must be on the same post.'),
            'is_approved' => $schema->boolean()->description('Publish immediately. Default false (pending).'),
        ];
    }
}
