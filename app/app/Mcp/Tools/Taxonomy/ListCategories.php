<?php

namespace App\Mcp\Tools\Taxonomy;

use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List post categories with their ids. Use the id as category_id in create-post and update-post.')]
#[IsReadOnly]
class ListCategories extends Tool
{
    use ChecksTokenAbility;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:read')) {
            return $error;
        }

        $categories = Category::orderBy('name')->get();

        if ($categories->isEmpty()) {
            return Response::text('No categories exist yet.');
        }

        return Response::text($categories->map(fn (Category $category): string => "- [{$category->id}] {$category->name} (slug: {$category->slug})")->implode("\n"));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
