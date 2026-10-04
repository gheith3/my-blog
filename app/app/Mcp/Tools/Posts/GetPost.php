<?php

namespace App\Mcp\Tools\Posts;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Mcp\Tools\Concerns\DescribesBlogRecords;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one post in full: title, slug, status, category, tags, thumbnail, public URL, and the complete content. Read it before editing so you change the right text.')]
#[IsReadOnly]
class GetPost extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:read')) {
            return $error;
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return Response::error('No post found with that post_id.');
        }

        return Response::text($this->postDetails($post));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
        ];
    }
}
