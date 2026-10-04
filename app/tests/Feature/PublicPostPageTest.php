<?php

use App\Enums\RevisionSource;
use App\Models\Post;
use App\Models\PostSlugRedirect;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a renamed slug permanently to the current post url', function () {
    $post = Post::factory()->published()->create();
    $oldSlug = $post->slug;

    app(PostService::class)->updatePost(
        $post,
        ['slug' => 'renamed-slug'],
        RevisionSource::Dashboard,
        'dashboard',
    );

    $this->get("/posts/{$oldSlug}")
        ->assertStatus(301)
        ->assertRedirect(route('posts.show', 'renamed-slug'));
});

it('serves the post at its current slug', function () {
    $post = Post::factory()->published()->create();

    $this->get("/posts/{$post->slug}")->assertOk();
});

it('keeps the 404 for unknown slugs', function () {
    $this->get('/posts/no-such-post')->assertNotFound();
});

it('redirects even when the redirect row was written for a post that changed again', function () {
    $post = Post::factory()->published()->create();
    $firstSlug = $post->slug;
    $service = app(PostService::class);

    $service->updatePost($post->refresh(), ['slug' => 'second-slug'], RevisionSource::Dashboard, 'dashboard');
    $service->updatePost($post->refresh(), ['slug' => 'third-slug'], RevisionSource::Dashboard, 'dashboard');

    $this->get("/posts/{$firstSlug}")
        ->assertStatus(301)
        ->assertRedirect(route('posts.show', 'third-slug'));

    expect(PostSlugRedirect::where('old_slug', $firstSlug)->sole()->post_id)->toBe($post->id);
});

it('feeds the excerpt to the meta description and og:description', function () {
    $post = Post::factory()->published()->create([
        'excerpt' => 'A handcrafted excerpt for the card.',
    ]);

    $this->get("/posts/{$post->slug}")
        ->assertOk()
        ->assertSee('<meta name="description" content="A handcrafted excerpt for the card.">', false)
        ->assertSee('<meta property="og:description" content="A handcrafted excerpt for the card.">', false);
});

it('generates the meta description from the first paragraph when the excerpt is empty', function () {
    $post = Post::factory()->published()->create([
        'content' => '<p>First paragraph of the story.</p><p>Second paragraph.</p>',
        'excerpt' => null,
    ]);

    $this->get("/posts/{$post->slug}")
        ->assertOk()
        ->assertSee('<meta name="description" content="First paragraph of the story.">', false);
});
