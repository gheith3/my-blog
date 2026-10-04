<?php

namespace App\Mcp\Tools\Comments;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Mcp\Tools\Concerns\DescribesBlogRecords;
use App\Models\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Edit a comment or moderate it. Pass comment_id plus only what changes: name, content, or is_approved (true to publish, false to hide again). Omitted fields are left as they are.')]
class UpdateComment extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'comments:write')) {
            return $error;
        }

        $comment = Comment::find($request->get('comment_id'));

        if ($comment === null) {
            return Response::error('No comment found with that comment_id.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first());
        }

        $comment->update(Arr::only($validator->validated(), ['name', 'content', 'is_approved']));

        return Response::text("Comment {$comment->id} updated.\n".$this->describeComment($comment->refresh()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'comment_id' => $schema->integer()->required()->description('The comment id, from list-comments.'),
            'name' => $schema->string(),
            'content' => $schema->string(),
            'is_approved' => $schema->boolean()->description('true to publish, false to hide.'),
        ];
    }
}
