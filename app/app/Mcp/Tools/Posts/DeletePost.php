<?php

namespace App\Mcp\Tools\Posts;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a post. Destructive: confirm with the user first. The post is soft-deleted (moved to trash), so it can be restored from the dashboard.')]
#[IsDestructive]
class DeletePost extends Tool
{
    use ChecksTokenAbility;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:write')) {
            return $error;
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return Response::error('No post found with that post_id.');
        }

        $title = $post->title;
        $post->delete();

        return Response::text("Post {$post->id} \"{$title}\" was moved to trash.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
        ];
    }
}
