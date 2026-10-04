<?php

namespace App\Mcp\Tools\Comments;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Mcp\Tools\Concerns\DescribesBlogRecords;
use App\Models\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one comment in full, including its complete text and reply count.')]
#[IsReadOnly]
class GetComment extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'comments:read')) {
            return $error;
        }

        $comment = Comment::withCount('replies')->find($request->get('comment_id'));

        if ($comment === null) {
            return Response::error('No comment found with that comment_id.');
        }

        return Response::text(implode("\n", [
            $this->describeComment($comment),
            "Replies: {$comment->replies_count}",
            '',
            $comment->content,
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'comment_id' => $schema->integer()->required()->description('The comment id, from list-comments.'),
        ];
    }
}
