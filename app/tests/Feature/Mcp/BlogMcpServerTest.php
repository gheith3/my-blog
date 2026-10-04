<?php

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

function callBlogTool(string $name, array $arguments = []): array
{
    $response = test()->postJson('/mcp/blog', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => $arguments],
    ], ['Accept' => 'application/json, text/event-stream']);

    return $response->json();
}

function blogToolText(array $response): string
{
    return $response['result']['content'][0]['text'];
}

it('rejects a request with no token', function () {
    $this->postJson('/mcp/blog', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream'])
        ->assertUnauthorized();
});

it('exposes the post and comment tools', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);

    $response = $this->postJson('/mcp/blog', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream']);

    $names = collect($response->json('result.tools'))->pluck('name');

    expect($names)->toContain('list-posts', 'create-post', 'update-post', 'delete-post')
        ->toContain('list-comments', 'create-comment', 'update-comment', 'delete-comment');
});

it('creates a draft post owned by the token user', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['posts:read', 'posts:write']);
    $category = Category::factory()->create();

    $response = callBlogTool('create-post', [
        'title' => 'Hello from an agent',
        'content' => 'Body text.',
        'category_id' => $category->id,
    ]);

    $post = Post::where('title', 'Hello from an agent')->firstOrFail();

    expect(blogToolText($response))->toContain("created as draft with id {$post->id}")
        ->and($post->user_id)->toBe($user->id)
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->published_at)->toBeNull();
});

it('refuses to create a post with a read-only token', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $category = Category::factory()->create();

    $response = callBlogTool('create-post', [
        'title' => 'Should not exist',
        'content' => 'Body text.',
        'category_id' => $category->id,
    ]);

    expect($response['result']['isError'])->toBeTrue()
        ->and(blogToolText($response))->toContain('`posts:write`')
        ->and(Post::where('title', 'Should not exist')->exists())->toBeFalse();
});

it('lists and reads posts with a read token', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $post = Post::factory()->published()->create(['title' => 'Readable post']);

    expect(blogToolText(callBlogTool('list-posts')))->toContain('Readable post')
        ->and(blogToolText(callBlogTool('get-post', ['post_id' => $post->id])))->toContain("Post [{$post->id}]: Readable post");
});

it('stamps published_at when a post is moved to published', function () {
    Passport::actingAs(User::factory()->create(), ['posts:write']);
    $post = Post::factory()->draft()->create();

    callBlogTool('update-post', [
        'post_id' => $post->id,
        'status' => 'published',
    ]);

    $post->refresh();

    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at)->not->toBeNull();
});

it('soft-deletes a post', function () {
    Passport::actingAs(User::factory()->create(), ['posts:write']);
    $post = Post::factory()->create();

    callBlogTool('delete-post', ['post_id' => $post->id]);

    expect(Post::find($post->id))->toBeNull()
        ->and(Post::withTrashed()->find($post->id))->not->toBeNull();
});

it('creates a pending comment and approves it', function () {
    Passport::actingAs(User::factory()->create(), ['comments:read', 'comments:write']);
    $post = Post::factory()->published()->create();

    $created = callBlogTool('create-comment', [
        'post_id' => $post->id,
        'name' => 'Visitor',
        'content' => 'Nice post.',
    ]);

    $comment = Comment::firstOrFail();

    expect(blogToolText($created))->toContain('Comment created with id')
        ->and($comment->is_approved)->toBeFalse()
        ->and($comment->commentable_id)->toBe($post->id);

    callBlogTool('update-comment', [
        'comment_id' => $comment->id,
        'is_approved' => true,
    ]);

    expect($comment->refresh()->is_approved)->toBeTrue();
});

it('rejects a reply whose parent belongs to another post', function () {
    Passport::actingAs(User::factory()->create(), ['comments:write']);
    $otherPost = Post::factory()->create();
    $parent = Comment::factory()->for($otherPost, 'commentable')->create();
    $post = Post::factory()->create();

    $response = callBlogTool('create-comment', [
        'post_id' => $post->id,
        'name' => 'Visitor',
        'content' => 'Reply.',
        'parent_id' => $parent->id,
    ]);

    expect($response['result']['isError'])->toBeTrue()
        ->and(blogToolText($response))->toContain('same post')
        ->and(Comment::where('content', 'Reply.')->exists())->toBeFalse();
});

it('soft-deletes a comment', function () {
    Passport::actingAs(User::factory()->create(), ['comments:write']);
    $comment = Comment::factory()->create();

    callBlogTool('delete-comment', ['comment_id' => $comment->id]);

    expect(Comment::find($comment->id))->toBeNull()
        ->and(Comment::withTrashed()->find($comment->id))->not->toBeNull();
});
