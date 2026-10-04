<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\PostStatus;
use App\Mcp\Tools\Concerns\ChecksTokenAbility;
use App\Mcp\Tools\Concerns\DescribesBlogRecords;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Edit a post. Pass post_id plus only the fields to change (title, content, category_id, status, thumbnail, tag_ids). Omitted fields are left as they are. Moving a post to published stamps its publish time if it has none.')]
class UpdatePost extends Tool
{
    use ChecksTokenAbility, DescribesBlogRecords;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureTokenAbility($request, 'posts:write')) {
            return $error;
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return Response::error('No post found with that post_id.');
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first());
        }

        $validated = $validator->validated();
        $changes = Arr::only($validated, ['title', 'content', 'category_id', 'thumbnail']);

        if (isset($validated['status'])) {
            $changes['status'] = PostStatus::from($validated['status']);

            if ($changes['status'] === PostStatus::Published && $post->published_at === null) {
                $changes['published_at'] = now();
            }
        }

        $post->update($changes);

        if (array_key_exists('tag_ids', $validated)) {
            $post->tags()->sync($validated['tag_ids']);
        }

        return Response::text("Post {$post->id} updated.\n\n".$this->postDetails($post->refresh()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
            'title' => $schema->string(),
            'content' => $schema->string(),
            'category_id' => $schema->integer()->description('From list-categories.'),
            'status' => $schema->string()->description('draft, published, or archived.'),
            'thumbnail' => $schema->string(),
            'tag_ids' => $schema->array()->items($schema->integer())->description('Replaces the post\'s tags with this list. Tag ids from list-tags.'),
        ];
    }
}
