<?php

namespace App\Mcp\Tools\Taxonomy;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List post tags with their ids. Use the ids as tag_ids in create-post and update-post.')]
#[IsReadOnly]
class ListTags extends Tool
{
    use ChecksTokenAbility;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:read')) {
            return $error;
        }

        $tags = Tag::orderBy('name')->get();

        if ($tags->isEmpty()) {
            return Response::text('No tags exist yet.');
        }

        return Response::text($tags->map(fn (Tag $tag): string => "- [{$tag->id}] {$tag->name} (slug: {$tag->slug})")->implode("\n"));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
