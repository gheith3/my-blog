<?php

use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates missing excerpts without bumping the version', function () {
    $post = Post::factory()->create([
        'content' => '<p>'.str_repeat('كلمة ', 100).'</p>',
        'excerpt' => null,
    ]);
    $version = $post->refresh()->version;

    $this->artisan('blog:backfill-v2')->assertSuccessful();

    $post->refresh();
    expect($post->excerpt)->not->toBeNull()
        ->and(mb_strlen($post->excerpt))->toBeLessThanOrEqual(300)
        ->and($post->excerpt)->toEndWith('…')
        ->and($post->version)->toBe($version);
});

it('writes a baseline revision for posts with no history', function () {
    $post = Post::factory()->create(['excerpt' => 'موجود']);

    $this->artisan('blog:backfill-v2')->assertSuccessful();

    $revision = $post->refresh()->revisions()->sole();
    expect($revision->state)->toBe(RevisionState::Applied)
        ->and($revision->source)->toBe(RevisionSource::Backfill)
        ->and($revision->version)->toBe($post->version)
        ->and($revision->title)->toBe($post->title)
        ->and($revision->content_html)->toBe($post->content)
        ->and($revision->excerpt)->toBe('موجود')
        ->and($revision->slug)->toBe($post->slug)
        ->and($revision->status)->toBe($post->status);
});

it('is idempotent and leaves posts with history untouched', function () {
    $post = Post::factory()->create(['excerpt' => null]);

    $this->artisan('blog:backfill-v2')->assertSuccessful();
    $excerpt = $post->refresh()->excerpt;

    $this->artisan('blog:backfill-v2')
        ->expectsOutputToContain('0 excerpts generated, 0 baseline revisions written')
        ->assertSuccessful();

    expect($post->refresh()->excerpt)->toBe($excerpt)
        ->and($post->revisions()->count())->toBe(1);
});

it('backfills archived posts too', function () {
    $post = Post::factory()->create(['excerpt' => null]);
    $post->delete();

    $this->artisan('blog:backfill-v2')->assertSuccessful();

    $post->refresh();
    expect($post->excerpt)->not->toBeNull()
        ->and($post->revisions()->count())->toBe(1);
});
