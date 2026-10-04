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

#[Description('Create a blog post. Requires a title, content, and category_id (from list-categories). Defaults to draft; set status to published to make it live. Returns the new post id and its slug.')]
class CreatePost extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:write')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first());
        }

        $validated = $validator->validated();
        $status = PostStatus::from($validated['status'] ?? PostStatus::Draft->value);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'thumbnail' => $validated['thumbnail'] ?? null,
            'status' => $status,
            'published_at' => $status === PostStatus::Published ? now() : null,
        ]);

        $post->tags()->sync($validated['tag_ids'] ?? []);

        return Response::text("Post created as {$status->value} with id {$post->id}.\n\n".$this->postDetails($post));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'content' => $schema->string()->required()->description('Full post body.'),
            'category_id' => $schema->integer()->required()->description('From list-categories.'),
            'status' => $schema->string()->description('draft (default), published, or archived.'),
            'thumbnail' => $schema->string()->description('Image URL or path, optional.'),
            'tag_ids' => $schema->array()->items($schema->integer())->description('Tag ids from list-tags.'),
        ];
    }
}
