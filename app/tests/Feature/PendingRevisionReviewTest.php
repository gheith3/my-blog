<?php

use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\RelationManagers\RevisionsRelationManager;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The dashboard language switcher defaults to ar; assert English strings.
    app()->setLocale('en');

    $this->actingAs(User::factory()->create());
});

it('shows the pending revision banner with a paragraph diff on the edit page', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>First paragraph stays.</p><p>Second paragraph old.</p>',
    ]);

    app(PostService::class)->stagePendingEdits(
        $post,
        '<p>First paragraph stays.</p><p>Second paragraph revised.</p>',
        [['old' => 'old.', 'new' => 'revised.']],
        RevisionSource::Mcp,
        'Claude',
        'Proofreading fixes',
    );

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertSee('Pending change from Claude')
        ->assertSee('Proofreading fixes')
        ->assertSee('dir="rtl"', false)
        ->assertSee('<del', false)
        ->assertSee('<mark', false)
        ->assertSee('old.')
        ->assertSee('revised.');
});

it('hides the banner when the post has no pending revisions', function () {
    $post = Post::factory()->draft()->create();

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertDontSee('Pending change from');
});

it('applies a pending revision on approve and bumps the version', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>First paragraph stays.</p><p>Second paragraph old.</p>',
    ]);

    $revision = app(PostService::class)->stagePendingEdits(
        $post,
        '<p>First paragraph stays.</p><p>Second paragraph revised.</p>',
        [['old' => 'old.', 'new' => 'revised.']],
        RevisionSource::Mcp,
        'Claude',
    );

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->callAction('approveRevision', arguments: ['revision' => $revision->id]);

    $post->refresh();
    $revision->refresh();

    expect($post->version)->toBe(2)
        ->and($post->content)->toBe('<p>First paragraph stays.</p><p>Second paragraph revised.</p>')
        ->and($revision->state)->toBe(RevisionState::Applied)
        ->and($revision->version)->toBe(2)
        ->and($revision->decided_by)->toBe(auth()->id());
});

it('marks a pending revision rejected without touching the live post', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>Live body.</p>',
    ]);

    $revision = app(PostService::class)->stagePendingEdits(
        $post,
        '<p>Staged body.</p>',
        [['old' => 'Live', 'new' => 'Staged']],
        RevisionSource::Mcp,
        'Claude',
    );

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->callAction('rejectRevision', arguments: ['revision' => $revision->id]);

    $post->refresh();
    $revision->refresh();

    expect($post->version)->toBe(1)
        ->and($post->content)->toBe('<p>Live body.</p>')
        ->and($revision->state)->toBe(RevisionState::Rejected)
        ->and($revision->decided_by)->toBe(auth()->id());
});

it('marks a stale whole-body pending revision as conflict on approve', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>Original body.</p>',
    ]);

    $revision = app(PostService::class)->stagePendingUpdate(
        $post,
        ['content' => '<p>Agent rewrite.</p>'],
        RevisionSource::Mcp,
        'Claude',
    );

    // The author edits the post before approving.
    app(PostService::class)->updatePost(
        $post,
        ['content' => '<p>Author edit.</p>'],
        RevisionSource::Dashboard,
        'dashboard',
    );

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->callAction('approveRevision', arguments: ['revision' => $revision->id]);

    $post->refresh();
    $revision->refresh();

    expect($post->version)->toBe(2)
        ->and($post->content)->toBe('<p>Author edit.</p>')
        ->and($revision->state)->toBe(RevisionState::Conflict)
        ->and($revision->decided_by)->toBe(auth()->id());
});

it('lists revisions newest first and restores an old one', function () {
    $author = User::factory()->create();
    $category = Category::factory()->create();
    $service = app(PostService::class);

    $post = $service->createPost([
        'user_id' => $author->id,
        'category_id' => $category->id,
        'title' => 'Original title',
        'content' => '<p>Original body.</p>',
        'status' => 'draft',
    ], RevisionSource::Dashboard, 'dashboard');

    $service->updatePost(
        $post,
        ['title' => 'Newer title', 'content' => '<p>Newer body.</p>'],
        RevisionSource::Dashboard,
        'dashboard',
    );

    $first = $post->revisions()->where('version', 1)->sole();
    $second = $post->revisions()->where('version', 2)->sole();

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])
        ->assertCanSeeTableRecords([$first, $second])
        ->callTableAction('restore', $first);

    $post->refresh();

    expect($post->title)->toBe('Original title')
        ->and($post->content)->toBe('<p>Original body.</p>')
        ->and($post->version)->toBe(3)
        ->and($post->revisions()->where('version', 3)->sole()->state)->toBe(RevisionState::Applied);
});

it('does not offer restore for pending revisions', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>Live body.</p>',
    ]);

    $revision = app(PostService::class)->stagePendingEdits(
        $post,
        '<p>Staged body.</p>',
        [['old' => 'Live', 'new' => 'Staged']],
        RevisionSource::Mcp,
        'Claude',
    );

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])->assertTableActionHidden('restore', $revision);
});

it('shows full revision details with the complete content in a modal', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>First paragraph stays.</p><p>Second paragraph old.</p>',
    ]);

    $revision = app(PostService::class)->stagePendingEdits(
        $post,
        '<p>First paragraph stays.</p><p>Second paragraph revised.</p>',
        [['old' => 'old.', 'new' => 'revised.']],
        RevisionSource::Mcp,
        'Claude',
        'Proofreading fixes',
    );

    $component = Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])->mountTableAction('viewDetails', $revision);

    // Filament renders action modals as a Livewire partial (wire:partial="action-modals").
    $modalHtml = $component->getMountedActionModalHtml();

    expect($modalHtml)->toContain($revision->id)
        ->toContain('Claude')
        ->toContain('Proofreading fixes')
        ->toContain('Second paragraph revised.')
        ->toContain('dir="rtl"');
});

it('approves and rejects pending revisions from the relation manager', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>Live body.</p>',
    ]);

    $service = app(PostService::class);

    $approved = $service->stagePendingEdits(
        $post,
        '<p>Approved body.</p>',
        [['old' => 'Live', 'new' => 'Approved']],
        RevisionSource::Mcp,
        'Claude',
    );

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])->callTableAction('approve', $approved);

    expect($post->refresh()->content)->toBe('<p>Approved body.</p>')
        ->and($approved->refresh()->state)->toBe(RevisionState::Applied)
        ->and($approved->decided_by)->toBe(auth()->id());

    $rejected = $service->stagePendingEdits(
        $post,
        '<p>Rejected body.</p>',
        [['old' => 'Approved', 'new' => 'Rejected']],
        RevisionSource::Mcp,
        'Claude',
    );

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])->callTableAction('reject', $rejected);

    expect($post->refresh()->content)->toBe('<p>Approved body.</p>')
        ->and($rejected->refresh()->state)->toBe(RevisionState::Rejected);
});

it('offers approve and reject only for pending revisions', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>Live body.</p>',
    ]);

    $pending = app(PostService::class)->stagePendingEdits(
        $post,
        '<p>Staged body.</p>',
        [['old' => 'Live', 'new' => 'Staged']],
        RevisionSource::Mcp,
        'Claude',
    );

    $applied = PostRevision::factory()->create([
        'post_id' => $post->id,
        'state' => RevisionState::Applied,
    ]);

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])
        ->assertTableActionVisible('approve', $pending)
        ->assertTableActionVisible('reject', $pending)
        ->assertTableActionHidden('approve', $applied)
        ->assertTableActionHidden('reject', $applied);
});

it('marks a stale pending revision as conflict when approved from the relation manager', function () {
    $post = Post::factory()->draft()->create([
        'content' => '<p>Original body.</p>',
    ]);

    $revision = app(PostService::class)->stagePendingUpdate(
        $post,
        ['content' => '<p>Agent rewrite.</p>'],
        RevisionSource::Mcp,
        'Claude',
    );

    app(PostService::class)->updatePost(
        $post,
        ['content' => '<p>Author edit.</p>'],
        RevisionSource::Dashboard,
        'dashboard',
    );

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $post->refresh(),
        'pageClass' => EditPost::class,
    ])->callTableAction('approve', $revision);

    expect($post->refresh()->content)->toBe('<p>Author edit.</p>')
        ->and($revision->refresh()->state)->toBe(RevisionState::Conflict);
});
