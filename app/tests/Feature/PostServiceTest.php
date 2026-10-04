<?php

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Exceptions\SlugTakenException;
use App\Exceptions\VersionConflictException;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\PostSlugRedirect;
use App\Models\User;
use App\Services\ContentEditor;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PostService;
});

function createViaService(array $overrides = [], ?User $user = null): Post
{
    $user ??= User::factory()->create();

    return (new PostService)->createPost(array_merge([
        'user_id' => $user->id,
        'category_id' => Category::factory()->create()->id,
        'title' => 'A test post',
        'content' => '<p>First paragraph of the post.</p><p>Second paragraph.</p>',
    ], $overrides), RevisionSource::Mcp, 'Claude');
}

it('creates a post at version 1 with an applied revision holding a full snapshot', function () {
    $post = createViaService(['title' => 'Hello', 'content' => '<p>Body.</p>']);

    expect($post->version)->toBe(1)
        ->and($post->excerpt)->toBe('Body.');

    $revision = $post->revisions()->sole();

    expect($revision->state)->toBe(RevisionState::Applied)
        ->and($revision->version)->toBe(1)
        ->and($revision->source)->toBe(RevisionSource::Mcp)
        ->and($revision->client_name)->toBe('Claude')
        ->and($revision->unguarded)->toBeTrue()
        ->and($revision->title)->toBe('Hello')
        ->and($revision->content_html)->toBe('<p>Body.</p>')
        ->and($revision->excerpt)->toBe('Body.')
        ->and($revision->slug)->toBe($post->slug)
        ->and($revision->status)->toBe(PostStatus::Draft);
});

it('keeps an explicit excerpt and honours a custom slug on create', function () {
    $post = createViaService(['excerpt' => 'Custom excerpt', 'slug' => 'my-slug']);

    expect($post->excerpt)->toBe('Custom excerpt')
        ->and($post->slug)->toBe('my-slug');
});

it('updates a post with a matching if_version and bumps the version', function () {
    $post = createViaService();

    $updated = $this->service->updatePost(
        $post,
        ['title' => 'New title'],
        RevisionSource::Mcp,
        'Claude',
        'Rename',
        ifVersion: 1,
    );

    expect($updated->version)->toBe(2)
        ->and($updated->title)->toBe('New title');

    $revision = $post->revisions()->where('version', 2)->sole();

    expect($revision->state)->toBe(RevisionState::Applied)
        ->and($revision->note)->toBe('Rename')
        ->and($revision->unguarded)->toBeFalse()
        ->and($revision->title)->toBe('New title');
});

it('rejects a stale if_version with a version_conflict carrying details', function () {
    $post = createViaService();

    $this->service->updatePost($post, ['title' => 'V2'], RevisionSource::Mcp, 'Claude', ifVersion: 1);

    try {
        $this->service->updatePost($post->fresh(), ['title' => 'V3'], RevisionSource::Api, ifVersion: 1);

        $this->fail('Expected VersionConflictException');
    } catch (VersionConflictException $e) {
        expect($e->errorCode)->toBe('version_conflict')
            ->and($e->details()['current_version'])->toBe(2)
            ->and($e->details()['source'])->toBe('mcp')
            ->and($e->details()['updated_at'])->not->toBeNull();
    }

    expect($post->fresh()->title)->toBe('V2')
        ->and($post->fresh()->version)->toBe(2)
        ->and($post->revisions()->count())->toBe(2);
});

it('flags revisions written without if_version as unguarded', function () {
    $post = createViaService();

    $this->service->updatePost($post, ['title' => 'Unguarded'], RevisionSource::Dashboard, 'dashboard');

    expect($post->revisions()->where('version', 2)->sole()->unguarded)->toBeTrue();
});

it('saves edits with the edits list stored on the revision', function () {
    $post = createViaService();
    $edits = [['old' => 'First', 'new' => '1st']];

    $updated = $this->service->saveEdits($post, '<p>1st paragraph of the post.</p>', RevisionSource::Mcp, 'Claude', ifVersion: 1, edits: $edits);

    expect($updated->version)->toBe(2)
        ->and($updated->content)->toBe('<p>1st paragraph of the post.</p>');

    $revision = $post->revisions()->where('version', 2)->sole();

    expect($revision->edits)->toBe($edits)
        ->and($revision->content_html)->toBe('<p>1st paragraph of the post.</p>');
});

it('stages a pending update without touching the live post', function () {
    $post = createViaService();

    $revision = $this->service->stagePendingUpdate(
        $post,
        ['title' => 'Staged title', 'content' => '<p>Staged body.</p>'],
        RevisionSource::Mcp,
        'Claude',
    );

    expect($revision->state)->toBe(RevisionState::Pending)
        ->and($revision->version)->toBeNull()
        ->and($revision->base_version)->toBe(1)
        ->and($revision->title)->toBe('Staged title')
        ->and($revision->content_html)->toBe('<p>Staged body.</p>');

    expect($post->fresh()->title)->toBe('A test post')
        ->and($post->fresh()->version)->toBe(1);
});

it('supersedes a previous pending revision from the same client only', function () {
    $post = createViaService();

    $first = $this->service->stagePendingUpdate($post, ['title' => 'One'], RevisionSource::Mcp, 'Claude');
    $second = $this->service->stagePendingUpdate($post, ['title' => 'Two'], RevisionSource::Mcp, 'Claude');
    $third = $this->service->stagePendingUpdate($post, ['title' => 'Three'], RevisionSource::Mcp, 'Other');

    expect($first->fresh()->state)->toBe(RevisionState::Superseded)
        ->and($second->fresh()->state)->toBe(RevisionState::Pending)
        ->and($third->fresh()->state)->toBe(RevisionState::Pending);
});

it('applies a pending revision on approve when the base version is current', function () {
    $post = createViaService();
    $author = User::factory()->create();

    $revision = $this->service->stagePendingEdits(
        $post,
        '<p>Proofread body.</p>',
        [['old' => 'First', 'new' => 'Proofread']],
        RevisionSource::Mcp,
        'Claude',
    );

    $approved = $this->service->approveRevision($revision, $author);
    $post->refresh();

    expect($approved->state)->toBe(RevisionState::Applied)
        ->and($approved->version)->toBe(2)
        ->and($approved->decided_by)->toBe($author->id)
        ->and($approved->decided_at)->not->toBeNull()
        ->and($post->version)->toBe(2)
        ->and($post->content)->toBe('<p>Proofread body.</p>');
});

it('marks a stale whole-body pending revision as conflict on approve', function () {
    $post = createViaService();
    $author = User::factory()->create();

    $revision = $this->service->stagePendingUpdate($post, ['title' => 'Staged', 'content' => '<p>Staged.</p>'], RevisionSource::Mcp, 'Claude');

    $this->service->updatePost($post, ['title' => 'Human edit'], RevisionSource::Dashboard, 'dashboard', ifVersion: 1);

    $approved = $this->service->approveRevision($revision->fresh(), $author);

    expect($approved->state)->toBe(RevisionState::Conflict)
        ->and($approved->version)->toBeNull();

    expect($post->fresh()->title)->toBe('Human edit')
        ->and($post->fresh()->version)->toBe(2);
});

it('replays a stale edit revision through the content editor when the edits still match', function () {
    $post = createViaService();
    $author = User::factory()->create();

    $revision = $this->service->stagePendingEdits(
        $post,
        '<p>Proofread paragraph of the post.</p>',
        [['old' => 'First', 'new' => 'Proofread']],
        RevisionSource::Mcp,
        'Claude',
    );

    $this->service->updatePost($post, ['title' => 'Human edit'], RevisionSource::Dashboard, 'dashboard', ifVersion: 1);

    $approved = $this->service->approveRevision($revision->fresh(), $author);

    expect($approved->state)->toBe(RevisionState::Applied)
        ->and($approved->version)->toBe(3)
        ->and($post->fresh()->content)->toContain('Proofread paragraph of the post.')
        ->and($post->fresh()->title)->toBe('Human edit');
});

it('replays a stale edit revision when the author edited a different paragraph meanwhile', function () {
    $post = createViaService([
        'content' => '<p>فقرة أولى فيها خطأ إملائي.</p><p>فقرة ثانية سليمة تماماً.</p><p>فقرة ثالثة للمراجعة.</p>',
    ]);
    $author = User::factory()->create();
    $editor = new ContentEditor;

    // The agent stages a fix to the first paragraph.
    $stagedEdits = [['old' => 'فيها خطأ إملائي', 'new' => 'فيه خطأ إملائي']];
    $revision = $this->service->stagePendingEdits(
        $post,
        $editor->apply($post->content, $stagedEdits),
        $stagedEdits,
        RevisionSource::Mcp,
        'Claude',
    );

    // The author edits a DIFFERENT paragraph through the same service layer.
    $authorHtml = $editor->apply($post->content, [['old' => 'للمراجعة', 'new' => 'للمراجعة النهائية']]);
    $this->service->saveEdits($post, $authorHtml, RevisionSource::Dashboard, 'dashboard', ifVersion: 1);

    expect($post->fresh()->version)->toBe(2)
        ->and($post->fresh()->content)->toContain('للمراجعة النهائية')
        ->and($post->fresh()->content)->toContain('فيها خطأ إملائي');

    $approved = $this->service->approveRevision($revision->fresh(), $author);

    expect($approved->state)->toBe(RevisionState::Applied)
        ->and($approved->version)->toBe(3);

    // The staged fix landed AND the author's paragraph change survived.
    expect($post->fresh()->version)->toBe(3)
        ->and($post->fresh()->content)->toBe(
            '<p>فقرة أولى فيه خطأ إملائي.</p><p>فقرة ثانية سليمة تماماً.</p><p>فقرة ثالثة للمراجعة النهائية.</p>',
        );
});

it('marks a stale edit revision as conflict when the edits no longer match', function () {
    $post = createViaService();
    $author = User::factory()->create();

    $revision = $this->service->stagePendingEdits(
        $post,
        '<p>Proofread paragraph of the post.</p>',
        [['old' => 'First', 'new' => 'Proofread']],
        RevisionSource::Mcp,
        'Claude',
    );

    $this->service->updatePost($post, ['content' => '<p>Completely rewritten body.</p>'], RevisionSource::Dashboard, 'dashboard', ifVersion: 1);

    $approved = $this->service->approveRevision($revision->fresh(), $author);

    expect($approved->state)->toBe(RevisionState::Conflict)
        ->and($approved->version)->toBeNull()
        ->and($post->fresh()->content)->toBe('<p>Completely rewritten body.</p>');
});

it('rejects a pending revision without touching the live post', function () {
    $post = createViaService();
    $author = User::factory()->create();

    $revision = $this->service->stagePendingUpdate($post, ['title' => 'Staged'], RevisionSource::Mcp, 'Claude');
    $rejected = $this->service->rejectRevision($revision, $author);

    expect($rejected->state)->toBe(RevisionState::Rejected)
        ->and($rejected->decided_by)->toBe($author->id)
        ->and($post->fresh()->title)->toBe('A test post')
        ->and($post->fresh()->version)->toBe(1);
});

it('restores a revision byte-identically and records a new revision', function () {
    $post = createViaService(['content' => '<p>Original body.</p>']);
    $original = $post->revisions()->sole();

    $this->service->updatePost($post, ['content' => '<p>Changed body.</p>', 'title' => 'Changed'], RevisionSource::Mcp, 'Claude', ifVersion: 1);

    $restored = $this->service->restoreRevision($post->fresh(), $original, ifVersion: 2);

    expect($restored->version)->toBe(3)
        ->and($restored->content)->toBe('<p>Original body.</p>')
        ->and($restored->title)->toBe('A test post')
        ->and($post->revisions()->count())->toBe(3)
        ->and($post->revisions()->where('version', 3)->sole()->content_html)->toBe('<p>Original body.</p>');
});

it('fails a guarded restore when the version moved on', function () {
    $post = createViaService();
    $original = $post->revisions()->sole();

    $this->service->updatePost($post, ['title' => 'V2'], RevisionSource::Mcp, ifVersion: 1);

    $this->service->restoreRevision($post->fresh(), $original, ifVersion: 1);
})->throws(VersionConflictException::class);

it('archives a post, soft-deletes it and keeps all revisions', function () {
    $post = createViaService();

    $archived = $this->service->archivePost($post, ifVersion: 1);

    expect($archived->status)->toBe(PostStatus::Archived)
        ->and($archived->trashed())->toBeTrue()
        ->and($post->revisions()->count())->toBe(2)
        ->and($post->revisions()->where('version', 2)->sole()->status)->toBe(PostStatus::Archived);
});

it('deletes permanently only with a matching if_version', function () {
    $post = createViaService();

    try {
        $this->service->deletePostPermanently($post, 99);

        $this->fail('Expected VersionConflictException');
    } catch (VersionConflictException) {
        expect(Post::withTrashed()->find($post->id))->not->toBeNull();
    }

    $this->service->deletePostPermanently($post, 1);

    expect(Post::withTrashed()->find($post->id))->toBeNull()
        ->and(PostRevision::where('post_id', $post->id)->count())->toBe(0);
});

it('rejects malformed slugs', function (string $slug) {
    createViaService(['slug' => $slug]);
})->with([
    'uppercase' => 'My-Slug',
    'spaces' => 'my slug',
    'symbols' => 'slug!',
    'too long' => str_repeat('a', 81),
])->throws(InvalidArgumentException::class);

it('rejects a slug already taken by another post with slug_taken details', function () {
    createViaService(['slug' => 'taken-slug']);

    try {
        createViaService(['slug' => 'taken-slug']);

        $this->fail('Expected SlugTakenException');
    } catch (SlugTakenException $e) {
        expect($e->errorCode)->toBe('slug_taken')
            ->and($e->details()['slug'])->toBe('taken-slug')
            ->and($e->details()['post_id'])->not->toBeNull();
    }
});

it('records a 301 redirect when a published post changes slug', function () {
    $post = createViaService([
        'slug' => 'old-slug',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $updated = $this->service->updatePost($post, ['slug' => 'new-slug'], RevisionSource::Mcp, 'Claude', ifVersion: 1);

    expect($updated->slug)->toBe('new-slug');

    $redirect = PostSlugRedirect::sole();

    expect($redirect->post_id)->toBe($post->id)
        ->and($redirect->old_slug)->toBe('old-slug');
});

it('does not record a redirect when a draft changes slug', function () {
    $post = createViaService(['slug' => 'draft-slug']);

    $this->service->updatePost($post, ['slug' => 'renamed-draft'], RevisionSource::Dashboard, 'dashboard', ifVersion: 1);

    expect(PostSlugRedirect::count())->toBe(0);
});

it('rejects a slug that is taken by a redirect', function () {
    $post = createViaService([
        'slug' => 'before-rename',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $this->service->updatePost($post, ['slug' => 'after-rename'], RevisionSource::Dashboard, 'dashboard', ifVersion: 1);

    createViaService(['slug' => 'before-rename']);
})->throws(SlugTakenException::class);

it('moves a published post with a future date to scheduled', function () {
    $post = createViaService([
        'status' => PostStatus::Published,
        'published_at' => now()->addWeek(),
    ]);

    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->published_at->isFuture())->toBeTrue();
});

it('stamps the current time when publishing without a date', function () {
    $post = createViaService(['status' => PostStatus::Published]);

    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at)->not->toBeNull()
        ->and($post->published_at->isPast())->toBeTrue();
});

it('moves a scheduled post to published once its date passes', function () {
    $post = createViaService([
        'status' => PostStatus::Published,
        'published_at' => now()->addWeek(),
    ]);

    $updated = $this->service->updatePost(
        $post,
        ['status' => PostStatus::Scheduled, 'published_at' => now()->subDay()],
        RevisionSource::Dashboard,
        'dashboard',
        ifVersion: 1,
    );

    expect($updated->status)->toBe(PostStatus::Published);
});

it('prunes revisions beyond 100 but never the first published or pending ones', function () {
    $post = createViaService([
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $firstPublished = $post->revisions()->sole();
    $pending = $this->service->stagePendingUpdate($post, ['title' => 'Staged'], RevisionSource::Mcp, 'Claude');

    foreach (range(1, 105) as $i) {
        $this->service->updatePost($post->fresh(), ['title' => "Title {$i}"], RevisionSource::Mcp, 'Claude');
    }

    expect($post->revisions()->count())->toBe(102)
        ->and(PostRevision::find($firstPublished->id))->not->toBeNull()
        ->and(PostRevision::find($pending->id)->state)->toBe(RevisionState::Pending);
});
