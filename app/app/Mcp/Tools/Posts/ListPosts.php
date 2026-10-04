<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Mcp\Tools\Concerns\DescribesBlogRecords;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List blog posts, newest first. Optionally filter by status (draft, published, archived). Returns each post\'s id, title, slug, status, and category. Use the id with get-post, update-post, and delete-post.')]
#[IsReadOnly]
class ListPosts extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:read')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first());
        }

        $posts = Post::query()
            ->with('category')
            ->when($request->get('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->limit((int) $request->get('limit', 50))
            ->get();

        if ($posts->isEmpty()) {
            return Response::text('No posts found.');
        }

        return Response::text($posts->map(fn (Post $post): string => $this->describePost($post))->implode("\n"));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Optional filter: draft, published, or archived.'),
            'limit' => $schema->integer()->description('Posts per call, default 50, max 100.'),
        ];
    }
}
