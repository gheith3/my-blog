<?php

use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates posts through PostService from the dashboard', function () {
    $category = Category::factory()->create();

    Livewire::test(CreatePost::class)
        ->set('data.title', 'Written in the dashboard')
        ->set('data.content', '<p>Dashboard body.</p>')
        ->set('data.category_id', $category->id)
        ->call('create');

    $post = Post::where('title', 'Written in the dashboard')->sole();

    expect($post->version)->toBe(1)
        ->and($post->excerpt)->toBe('Dashboard body.')
        ->and($post->revisions()->sole()->state)->toBe(RevisionState::Applied)
        ->and($post->revisions()->sole()->source)->toBe(RevisionSource::Dashboard);
});

it('bumps the version and writes a revision when saving from the dashboard', function () {
    $post = Post::factory()->draft()->create();

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->set('data.title', 'Edited in the dashboard')
        ->call('save');

    $post->refresh();

    $revision = $post->revisions()->where('version', 2)->sole();

    expect($post->version)->toBe(2)
        ->and($post->title)->toBe('Edited in the dashboard')
        ->and($revision->source)->toBe(RevisionSource::Dashboard)
        ->and($revision->client_name)->toBe('dashboard')
        ->and($revision->unguarded)->toBeFalse()
        ->and($revision->title)->toBe('Edited in the dashboard');
});
