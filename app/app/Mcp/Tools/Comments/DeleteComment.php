<?php

namespace App\Mcp\Tools\Comments;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Models\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a comment. Destructive: confirm with the user first. The comment is soft-deleted, not erased. To hide it without deleting, use update-comment with is_approved false.')]
#[IsDestructive]
class DeleteComment extends Tool
{
    use ChecksTokenAbility;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'comments:write')) {
            return $error;
        }

        $comment = Comment::find($request->get('comment_id'));

        if ($comment === null) {
            return Response::error('No comment found with that comment_id.');
        }

        $comment->delete();

        return Response::text("Comment {$comment->id} was moved to trash.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'comment_id' => $schema->integer()->required()->description('The comment id, from list-comments.'),
        ];
    }
}
