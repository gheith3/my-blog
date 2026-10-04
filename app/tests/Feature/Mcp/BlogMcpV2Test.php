<?php

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\PostSlugRedirect;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

function writeToken(): void
{
    Passport::actingAs(User::factory()->create(), ['posts:read', 'posts:write']);
}

/*
|--------------------------------------------------------------------------
| edit-post (spec section 4)
|--------------------------------------------------------------------------
*/

it('applies Arabic edits atomically, bumps the version once and writes one revision', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>سحبت يداه من التوغل.</p><p>أبلع قراراً صغير لحماية لطفل لم يأت.</p>',
    ])->refresh();

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'if_version' => $post->version,
        'note' => 'Proofreading: tanween, hamza',
        'edits' => [
            ['old' => 'سحبت يداه', 'new' => 'سحبتُ يديه'],
            ['old' => 'لحماية لطفل', 'new' => 'لحماية طفل'],
        ],
    ]);

    $payload = blogToolPayload($response);

    expect($response['result']['isError'])->toBeFalse()
        // v2 responses are dual-published: JSON text plus MCP structuredContent.
        ->and($response['result']['structuredContent']['revision_id'])->toBe($payload['revision_id'])
        ->and($payload['post_id'])->toBe($post->id)
        ->and($payload['version'])->toBe($post->version + 1)
        ->and($payload['status'])->toBe('draft')
        ->and($payload['applied'])->toBe(2)
        ->and($payload['changed_paragraphs'])->toBe([1, 2])
        ->and($payload['revision_id'])->not->toBeNull()
        ->and($payload['summary'])->toContain('2 edits')
        ->and($payload)->not->toHaveKey('content')
        ->and(strlen(blogToolText($response)))->toBeLessThan(1024);

    $post->refresh();

    expect($post->content)->toBe('<p>سحبتُ يديه من التوغل.</p><p>أبلع قراراً صغير لحماية طفل لم يأت.</p>');

    $revision = $post->revisions()->sole();

    expect($revision->state)->toBe(RevisionState::Applied)
        ->and($revision->source)->toBe(RevisionSource::Mcp)
        ->and($revision->client_name)->toBe('mcp')
        ->and($revision->note)->toBe('Proofreading: tanween, hamza')
        ->and($revision->unguarded)->toBeFalse()
        ->and($revision->version)->toBe($post->version)
        ->and($revision->edits)->toHaveCount(2);
});

it('applies over fifty edits across a long Arabic body in one atomic call', function () {
    writeToken();

    $paragraphs = [];

    for ($i = 1; $i <= 54; $i++) {
        $paragraphs[] = "<p style=\"text-align: justify;\">الفقرة {$i}: قال أيضا كلاماً طويلاً عن البحر والسماء.</p>";
    }

    $post = Post::factory()->draft()->create(['content' => implode('', $paragraphs)])->refresh();

    $edits = [];

    for ($i = 1; $i <= 54; $i++) {
        $edits[] = ['old' => 'قال أيضا كلاماً', 'new' => 'قال أيضاً كلاماً', 'paragraph' => $i];
    }

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'if_version' => $post->version,
        'note' => 'Proofreading: tanween',
        'edits' => $edits,
    ]);

    $payload = blogToolPayload($response);

    expect($response['result']['isError'])->toBeFalse()
        ->and($payload['version'])->toBe($post->version + 1)
        ->and($payload['applied'])->toBe(54)
        ->and($payload['changed_paragraphs'])->toHaveCount(54)
        ->and(strlen(blogToolText($response)))->toBeLessThan(1024);

    $post->refresh();

    expect($post->version)->toBe(2)
        ->and($post->revisions()->count())->toBe(1)
        ->and($post->content)->toBe(str_replace('قال أيضا كلاماً', 'قال أيضاً كلاماً', implode('', $paragraphs)));
});

it('writes nothing and lists every failing edit when one edit is bad', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>نص أول فيه كلمة.</p><p>نص ثانٍ هنا.</p>',
    ]);
    $original = $post->content;

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [
            ['old' => 'فيه كلمة', 'new' => 'فيهِ كلمة'],
            ['old' => 'لا وجود له', 'new' => 'شيء'],
            ['old' => 'نص', 'new' => 'كلام'],
        ],
    ]);

    $error = blogToolError($response);

    expect($error['code'])->toBe('edit_failed')
        ->and($error['details']['failures'])->toHaveCount(2)
        ->and($error['details']['failures'][0]['index'])->toBe(1)
        ->and($error['details']['failures'][0]['code'])->toBe('edit_not_found')
        ->and($error['details']['failures'][1]['index'])->toBe(2)
        ->and($error['details']['failures'][1]['code'])->toBe('edit_ambiguous')
        ->and($error['details']['failures'][1]['count'])->toBe(2)
        ->and($error['details']['failures'][1]['paragraphs'])->toBe([1, 2]);

    $post->refresh();

    expect($post->content)->toBe($original)
        ->and($post->version)->toBe(1)
        ->and($post->revisions()->count())->toBe(0);
});

it('matches a quote written as " against text stored as &quot;', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>قال: &quot;انت نيتك تفضحينا؟&quot; فصمتت.</p>',
    ]);

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => '"انت نيتك تفضحينا؟"', 'new' => '"انت نيتك تفضحينا!"']],
    ]);

    expect($response['result']['isError'])->toBeFalse()
        ->and($post->refresh()->content)->toBe('<p>قال: &quot;انت نيتك تفضحينا!&quot; فصمتت.</p>');
});

it('fails an ambiguous edit and applies it once occurrence is given', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>قال أيضا شيئا.</p><p>ثم قال أيضا أخرى.</p>',
    ]);

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'أيضا', 'new' => 'أيضاً']],
    ]));

    expect($error['details']['failures'][0]['code'])->toBe('edit_ambiguous')
        ->and($error['details']['failures'][0]['count'])->toBe(2);

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'أيضا', 'new' => 'أيضاً', 'occurrence' => 2]],
    ]);

    expect($response['result']['isError'])->toBeFalse()
        ->and($post->refresh()->content)->toBe('<p>قال أيضا شيئا.</p><p>ثم قال أيضاً أخرى.</p>');
});

it('does not match مباغتا inside مباغتاً when whole_word is set', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>كان القرار مباغتاً للجميع.</p>',
    ]);

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'whole_word' => true,
        'edits' => [['old' => 'مباغتا', 'new' => 'مفاجئا']],
    ]));

    expect($error['details']['failures'][0]['code'])->toBe('edit_not_found');

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'مباغتا', 'new' => 'مفاجئا']],
    ]);

    expect($response['result']['isError'])->toBeFalse()
        ->and($post->refresh()->content)->toContain('مفاجئاً');
});

it('returns a near match naming U+200F when the old string differs only by it', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>ولا تستأذن'."\u{200F}".' أحد، بل امضِ.</p>',
    ]);

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'ولا تستأذن أحد،', 'new' => 'ولا تستأذن أحداً،']],
    ]));

    $failure = $error['details']['failures'][0];

    expect($failure['code'])->toBe('edit_not_found')
        ->and($failure['near_matches'])->not->toBeEmpty()
        ->and($failure['near_matches'][0]['difference'])->toContain('U+200F');
});

it('escapes markup in the new text instead of injecting it', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>نص عادي هنا.</p>',
    ]);

    $response = callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'عادي', 'new' => '<b>عادي</b>']],
    ]);

    expect($response['result']['isError'])->toBeFalse();

    $post->refresh();

    expect($post->content)->toContain('&lt;b&gt;عادي&lt;/b&gt;')
        ->and($post->content)->not->toContain('<b>');
});

it('preserves block style attributes through an edit', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p style="text-align: justify;">خطٌّ واحد فقط.</p>',
    ]);

    callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'واحد', 'new' => 'واحد تماماً']],
    ]);

    expect($post->refresh()->content)->toContain('style="text-align: justify;"')
        ->and($post->content)->toContain('واحد تماماً');
});

it('rejects a stale if_version with version_conflict and writes nothing', function () {
    writeToken();
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>'])->refresh();
    $original = $post->content;

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'if_version' => $post->version + 3,
        'edits' => [['old' => 'نص', 'new' => 'كلام']],
    ]));

    expect($error['code'])->toBe('version_conflict')
        ->and($error['details']['current_version'])->toBe($post->version)
        ->and($error['details'])->toHaveKeys(['updated_at', 'source']);

    $post->refresh();

    expect($post->content)->toBe($original)
        ->and($post->revisions()->count())->toBe(0);
});

it('fails a guarded write when a dashboard save lands between read and write', function () {
    writeToken();
    $post = Post::factory()->draft()->create(['content' => '<p>قبل التعديل.</p>'])->refresh();
    $readVersion = $post->version;

    // The author saves through the same service layer, bumping the version.
    app(PostService::class)->updatePost(
        $post,
        ['title' => 'تدخل المؤلف'],
        RevisionSource::Dashboard,
    );

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'if_version' => $readVersion,
        'edits' => [['old' => 'قبل', 'new' => 'بعد']],
    ]));

    expect($error['code'])->toBe('version_conflict')
        ->and($error['details']['current_version'])->toBe($readVersion + 1)
        ->and($error['details']['source'])->toBe('dashboard')
        ->and($post->refresh()->content)->toBe('<p>قبل التعديل.</p>');
});

it('returns a diff on dry_run and saves nothing', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>أبلع قراراً صغير لحماية لطفل لم يأت.</p>',
    ]);
    $original = $post->content;

    $payload = blogToolPayload(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'dry_run' => true,
        'edits' => [['old' => 'لحماية لطفل', 'new' => 'لحماية طفل']],
    ]));

    expect($payload['dry_run'])->toBeTrue()
        ->and($payload['applied'])->toBe(1)
        ->and($payload['diff'])->toHaveCount(1)
        ->and($payload['diff'][0]['paragraph'])->toBe(1)
        ->and($payload['diff'][0]['before'])->toContain('لحماية لطفل')
        ->and($payload['diff'][0]['after'])->toContain('لحماية طفل');

    $post->refresh();

    expect($post->content)->toBe($original)
        ->and($post->version)->toBe(1)
        ->and($post->revisions()->count())->toBe(0);
});

it('stages a pending change that leaves the live post untouched', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>أبلع قراراً صغير لحماية لطفل لم يأت.</p>',
    ])->refresh();
    $original = $post->content;

    $payload = blogToolPayload(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'apply_to' => 'pending',
        'edits' => [['old' => 'لحماية لطفل', 'new' => 'لحماية طفل']],
    ]));

    expect($payload['pending'])->toBeTrue()
        ->and($payload['revision_id'])->not->toBeNull()
        ->and($payload['version'])->toBe($post->version);

    $post->refresh();

    expect($post->content)->toBe($original)
        ->and($post->version)->toBe(1);

    $revision = PostRevision::findOrFail($payload['revision_id']);

    expect($revision->state)->toBe(RevisionState::Pending)
        ->and($revision->base_version)->toBe(1)
        ->and($revision->version)->toBeNull()
        ->and($revision->content_html)->toContain('لحماية طفل')
        ->and($revision->edits)->toHaveCount(1);

    $list = blogToolPayload(callBlogTool('list-revisions', [
        'post_id' => $post->id,
        'state' => 'pending',
    ]));

    expect($list['revisions'])->toHaveCount(1)
        ->and($list['revisions'][0]['revision_id'])->toBe($revision->id)
        ->and($list['revisions'][0])->not->toHaveKey('content');
});

it('rejects more than 200 edits with payload_too_large', function () {
    writeToken();
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>']);

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => array_fill(0, 201, ['old' => 'نص', 'new' => 'كلام']),
    ]));

    expect($error['code'])->toBe('payload_too_large')
        ->and($error['details']['limit'])->toBe('200 edits per call')
        ->and($error['details']['actual'])->toBe(201);
});

/*
|--------------------------------------------------------------------------
| revisions (spec section 6)
|--------------------------------------------------------------------------
*/

it('restores a revision byte-identically and adds a new revision', function () {
    writeToken();
    $original = '<p style="text-align: justify;">النص الأصلي &quot;كاملاً&quot; هنا.</p>';
    $category = Category::factory()->create();

    $created = blogToolPayload(callBlogTool('create-post', [
        'title' => 'قصة قصيرة',
        'content' => $original,
        'category_id' => $category->id,
    ]));

    $post = Post::findOrFail($created['post_id']);
    $firstRevisionId = $created['revision_id'];

    callBlogTool('update-post', [
        'post_id' => $post->id,
        'content' => '<p>نص بديل بالكامل.</p>',
    ]);

    expect($post->refresh()->content)->toBe('<p>نص بديل بالكامل.</p>');

    $restored = blogToolPayload(callBlogTool('restore-revision', [
        'post_id' => $post->id,
        'revision_id' => $firstRevisionId,
        'if_version' => $post->version,
    ]));

    expect($restored['version'])->toBe(3);

    $post->refresh();

    expect($post->content)->toBe($original)
        ->and($post->revisions()->count())->toBe(3)
        ->and($post->revisions()->orderByDesc('id')->first()->state)->toBe(RevisionState::Applied);
});

it('reads a revision with a paragraph-level diff against the live post', function () {
    writeToken();
    $post = Post::factory()->draft()->create([
        'content' => '<p>فقرة أولى ثابتة.</p><p>فقرة ثانية قبل التعديل.</p>',
    ]);

    $edit = blogToolPayload(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'قبل التعديل', 'new' => 'بعد التعديل']],
    ]));

    $revision = blogToolPayload(callBlogTool('get-revision', [
        'revision_id' => $edit['revision_id'],
        'format' => 'text',
        'diff_against' => 'live',
    ]));

    expect($revision['revision_id'])->toBe($edit['revision_id'])
        ->and($revision['content'])->toContain('بعد التعديل')
        ->and($revision['diff'])->toBe([]);

    // Diff the first revision (pre-edit snapshot does not exist; the factory
    // wrote no revision) — instead diff an old revision against live.
    $older = blogToolPayload(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'فقرة أولى ثابتة', 'new' => 'فقرة أولى معدلة']],
    ]));

    $previous = blogToolPayload(callBlogTool('get-revision', [
        'revision_id' => $edit['revision_id'],
        'format' => 'text',
        'diff_against' => 'previous',
    ]));

    expect($previous['diff_against'])->toBe('previous');

    $live = blogToolPayload(callBlogTool('get-revision', [
        'revision_id' => $edit['revision_id'],
        'diff_against' => 'live',
    ]));

    expect($live['diff'])->toHaveCount(1)
        ->and($live['diff'][0]['paragraph'])->toBe(1)
        ->and($live['diff'][0]['before'])->toContain('معدلة')
        ->and($live['diff'][0]['after'])->toContain('ثابتة')
        ->and($older['revision_id'])->not->toBe($edit['revision_id']);
});

it('requires posts:read for revision reads and posts:write for restore', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>']);

    expect(blogToolError(callBlogTool('restore-revision', [
        'post_id' => $post->id,
        'revision_id' => 'whatever',
    ]))['code'])->toBe('forbidden');
});

/*
|--------------------------------------------------------------------------
| update-post / create-post / delete-post (spec section 5)
|--------------------------------------------------------------------------
*/

it('returns a lean receipt by default and the full post when verbose', function () {
    writeToken();
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>']);

    $lean = blogToolPayload(callBlogTool('update-post', [
        'post_id' => $post->id,
        'title' => 'عنوان جديد',
    ]));

    expect($lean['version'])->toBe(2)
        ->and($lean['revision_id'])->not->toBeNull()
        ->and($lean)->not->toHaveKey('content');

    $full = blogToolPayload(callBlogTool('update-post', [
        'post_id' => $post->id,
        'title' => 'عنوان أحدث',
        'verbose' => true,
    ]));

    expect($full['version'])->toBe(3)
        ->and($full['content'])->toBe('<p>نص.</p>')
        ->and($full['title'])->toBe('عنوان أحدث')
        ->and($full['word_count'])->toBeGreaterThan(0);
});

it('rejects pending updates that carry metadata other than title and content', function () {
    writeToken();
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>']);

    $error = blogToolError(callBlogTool('update-post', [
        'post_id' => $post->id,
        'apply_to' => 'pending',
        'title' => 'عنوان مؤجل',
        'status' => 'published',
    ]));

    expect($error['code'])->toBe('invalid_param')
        ->and($error['details']['param'])->toBe('apply_to');

    expect($post->refresh()->title)->not->toBe('عنوان مؤجل')
        ->and($post->revisions()->count())->toBe(0);
});

it('stages a pending title change without touching the live post', function () {
    writeToken();
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>', 'title' => 'العنوان الحالي']);

    $payload = blogToolPayload(callBlogTool('update-post', [
        'post_id' => $post->id,
        'apply_to' => 'pending',
        'title' => 'العنوان المقترح',
    ]));

    expect($payload['pending'])->toBeTrue()
        ->and($post->refresh()->title)->toBe('العنوان الحالي')
        ->and(PostRevision::findOrFail($payload['revision_id'])->title)->toBe('العنوان المقترح');
});

it('generates an excerpt that ends on a word boundary when none is given', function () {
    writeToken();
    $category = Category::factory()->create();
    $words = implode(' ', array_fill(0, 80, 'كلمة'));
    $content = "<p>{$words}</p>";

    $created = blogToolPayload(callBlogTool('create-post', [
        'title' => 'بدون مقتطف',
        'content' => $content,
        'category_id' => $category->id,
    ]));

    $post = Post::findOrFail($created['post_id']);

    expect($post->excerpt)->toEndWith('…')
        ->and(mb_strlen($post->excerpt))->toBeLessThanOrEqual(300)
        ->and(str_ends_with($post->excerpt, 'ة…'))->toBeTrue();

    $explicit = blogToolPayload(callBlogTool('create-post', [
        'title' => 'بمقتطف',
        'content' => $content,
        'category_id' => $category->id,
        'excerpt' => 'مقتطف مخصص.',
    ]));

    expect(Post::findOrFail($explicit['post_id'])->excerpt)->toBe('مقتطف مخصص.');
});

it('keeps the old slug as a redirect when a published post is renamed', function () {
    writeToken();
    $post = Post::factory()->published()->create(['slug' => 'old-slug', 'content' => '<p>نص.</p>']);

    blogToolPayload(callBlogTool('update-post', [
        'post_id' => $post->id,
        'slug' => 'new-slug',
    ]));

    expect($post->refresh()->slug)->toBe('new-slug');

    $redirect = PostSlugRedirect::where('old_slug', 'old-slug')->sole();

    expect($redirect->post_id)->toBe($post->id);

    $resolved = blogToolPayload(callBlogTool('get-post', ['slug' => 'old-slug']));

    expect($resolved['post_id'])->toBe($post->id)
        ->and($resolved['slug'])->toBe('new-slug');
});

it('rejects a taken slug with slug_taken', function () {
    writeToken();
    $other = Post::factory()->draft()->create(['slug' => 'taken-slug']);
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>']);

    $error = blogToolError(callBlogTool('update-post', [
        'post_id' => $post->id,
        'slug' => 'taken-slug',
    ]));

    expect($error['code'])->toBe('slug_taken')
        ->and($error['details']['slug'])->toBe('taken-slug')
        ->and($error['details']['post_id'])->toBe($other->id);
});

it('archives by default and permanently deletes only with a matching if_version', function () {
    writeToken();
    $post = Post::factory()->published()->create(['content' => '<p>نص.</p>']);

    $archived = blogToolPayload(callBlogTool('delete-post', ['post_id' => $post->id]));

    expect($archived['status'])->toBe('archived')
        ->and(Post::find($post->id))->toBeNull()
        ->and(Post::withTrashed()->find($post->id)->status)->toBe(PostStatus::Archived)
        ->and(PostRevision::where('post_id', $post->id)->count())->toBe(1);

    $error = blogToolError(callBlogTool('delete-post', [
        'post_id' => $post->id,
        'permanent' => true,
    ]));

    expect($error['code'])->toBe('invalid_param')
        ->and($error['details']['param'])->toBe('if_version');

    $conflict = blogToolError(callBlogTool('delete-post', [
        'post_id' => $post->id,
        'permanent' => true,
        'if_version' => 99,
    ]));

    expect($conflict['code'])->toBe('version_conflict');

    $deleted = blogToolPayload(callBlogTool('delete-post', [
        'post_id' => $post->id,
        'permanent' => true,
        'if_version' => Post::withTrashed()->find($post->id)->version,
    ]));

    expect($deleted['deleted'])->toBeTrue()
        ->and(Post::withTrashed()->find($post->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| reading and lookup (spec section 7)
|--------------------------------------------------------------------------
*/

it('finds a post by id, slug or full url and rejects ambiguous locators', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $post = Post::factory()->published()->create(['content' => '<p>نص القصة.</p>']);

    $byId = blogToolPayload(callBlogTool('get-post', ['post_id' => $post->id]));
    $bySlug = blogToolPayload(callBlogTool('get-post', ['slug' => $post->slug]));
    $byUrl = blogToolPayload(callBlogTool('get-post', ['url' => "https://gheith.me/posts/{$post->slug}"]));

    expect($byId['post_id'])->toBe($post->id)
        ->and($bySlug['post_id'])->toBe($post->id)
        ->and($byUrl['post_id'])->toBe($post->id)
        ->and($byId)->toHaveKeys(['version', 'excerpt', 'word_count', 'reading_time_minutes', 'published_at', 'updated_at', 'url']);

    expect(blogToolError(callBlogTool('get-post'))['code'])->toBe('invalid_param')
        ->and(blogToolError(callBlogTool('get-post', [
            'post_id' => $post->id,
            'slug' => $post->slug,
        ]))['code'])->toBe('invalid_param')
        ->and(blogToolError(callBlogTool('get-post', ['slug' => 'no-such-slug']))['code'])->toBe('not_found');
});

it('returns HTML that writes back unchanged byte-identically', function () {
    writeToken();
    $original = '<p style="text-align: justify;">قال: &quot;انت نيتك تفضحينا؟&quot; فصمتت، وكانت السَّماءُ صافيةً.</p>'
        .'<p style="text-align: justify;">خطٌّ واحد، والبحرُ أمامنا، والليلُ طويلٌ جداً.</p>';
    $post = Post::factory()->draft()->create(['content' => $original]);

    $read = blogToolPayload(callBlogTool('get-post', [
        'post_id' => $post->id,
        'format' => 'html',
    ]));

    expect($read['content'])->toBe($original);

    $written = blogToolPayload(callBlogTool('update-post', [
        'post_id' => $post->id,
        'if_version' => $read['version'],
        'content' => $read['content'],
    ]));

    expect($written['version'])->toBe($read['version'] + 1)
        ->and($post->refresh()->content)->toBe($original);
});

it('returns the text view with entities decoded and harakat kept', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $post = Post::factory()->published()->create([
        'content' => '<p>قال: &quot;مرحباً&quot; بالضَّميرِ.</p><p>سطرٌ ثانٍ.</p>',
    ]);

    $payload = blogToolPayload(callBlogTool('get-post', [
        'post_id' => $post->id,
        'format' => 'text',
    ]));

    expect($payload['format'])->toBe('text')
        ->and($payload['content'])->toBe('قال: "مرحباً" بالضَّميرِ.'."\n\n".'سطرٌ ثانٍ.');
});

it('returns paragraphs with 1-based indexes and can omit per-block html', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $post = Post::factory()->published()->create([
        'content' => '<p>أولى.</p><p style="text-align: justify;">ثانية.</p>',
    ]);

    $payload = blogToolPayload(callBlogTool('get-post', [
        'post_id' => $post->id,
        'format' => 'paragraphs',
        'include_html' => false,
    ]));

    expect($payload['blocks'])->toHaveCount(2)
        ->and($payload['blocks'][0]['index'])->toBe(1)
        ->and($payload['blocks'][0]['text'])->toBe('أولى.')
        ->and($payload['blocks'][0])->not->toHaveKey('html')
        ->and($payload['blocks'][1]['type'])->toBe('p');
});

it('reads a batch of posts and truncates bodies on request', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $long = Post::factory()->published()->create(['content' => '<p>'.implode(' ', array_fill(0, 60, 'نص')).'</p>']);
    $short = Post::factory()->published()->create(['content' => '<p>قصير.</p>']);

    $payload = blogToolPayload(callBlogTool('get-posts', [
        'post_ids' => [$long->id, $short->id],
        'max_chars_per_post' => 50,
    ]));

    expect($payload['posts'])->toHaveCount(2);

    $posts = collect($payload['posts'])->keyBy('post_id');

    expect($payload['posts'][0]['format'])->toBe('text')
        ->and($posts[$long->id]['truncated'])->toBeTrue()
        ->and(mb_strlen($posts[$long->id]['content']))->toBe(50)
        ->and($posts[$short->id]['truncated'] ?? false)->toBeFalse();

    $error = blogToolError(callBlogTool('get-posts', [
        'post_ids' => range(1, 21),
    ]));

    expect($error['code'])->toBe('payload_too_large');
});

it('finds أخطاء when searching for اخطاء and never normalizes snippets', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $hit = Post::factory()->published()->create([
        'title' => 'عنوان عادي',
        'content' => '<p>رتّبنا الأخطاء الصغيرة قبل النشر، ثم راجعناها مرة أخرى.</p>',
    ]);
    Post::factory()->published()->create([
        'title' => 'لا علاقة له',
        'content' => '<p>نص لا يمت للبحث بصلة.</p>',
    ]);

    $payload = blogToolPayload(callBlogTool('search-posts', ['query' => 'اخطاء']));

    expect($payload['results'])->toHaveCount(1);

    $result = $payload['results'][0];

    expect($result['post_id'])->toBe($hit->id)
        ->and($result['match_count'])->toBe(1)
        ->and($result['snippets'])->toHaveCount(1)
        ->and($result['snippets'][0])->toContain('الأخطاء')
        ->and(mb_strlen($result['snippets'][0]))->toBeLessThanOrEqual(125);

    $byTitle = blogToolPayload(callBlogTool('search-posts', ['query' => 'عنوان']));

    expect(collect($byTitle['results'])->pluck('post_id'))->toContain($hit->id);
});

it('paginates list-posts with a cursor', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    Post::factory()->count(5)->create(['content' => '<p>نص.</p>']);

    $page1 = blogToolPayload(callBlogTool('list-posts', ['limit' => 2]));

    expect($page1['posts'])->toHaveCount(2)
        ->and($page1['next_cursor'])->not->toBeNull()
        ->and($page1['posts'][0])->toHaveKeys(['version', 'word_count', 'published_at', 'updated_at']);

    $page2 = blogToolPayload(callBlogTool('list-posts', ['limit' => 2, 'cursor' => $page1['next_cursor']]));

    expect($page2['posts'])->toHaveCount(2);

    $page3 = blogToolPayload(callBlogTool('list-posts', ['limit' => 2, 'cursor' => $page2['next_cursor']]));

    expect($page3['posts'])->toHaveCount(1)
        ->and($page3['next_cursor'])->toBeNull();

    $ids = collect($page1['posts'])->merge($page2['posts'])->merge($page3['posts'])->pluck('post_id');

    expect($ids->unique())->toHaveCount(5);

    expect(blogToolError(callBlogTool('list-posts', ['cursor' => 'not-a-cursor']))['code'])->toBe('invalid_param');
});

/*
|--------------------------------------------------------------------------
| style guide (spec section 8) and scopes
|--------------------------------------------------------------------------
*/

it('serves the style guide as a tool and as a resource', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);

    $payload = blogToolPayload(callBlogTool('get-style-guide'));

    expect($payload['content'])->toContain('# House style')
        ->and($payload['version'])->toBe(1)
        ->and($payload['updated_at'])->not->toBeNull();

    $resource = $this->postJson('/mcp/blog', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => ['uri' => 'blog://style-guide'],
    ], ['Accept' => 'application/json, text/event-stream']);

    expect($resource->json('result.contents.0.text'))->toContain('# House style')
        ->and($resource->json('result.contents.0.mimeType'))->toBe('text/markdown');
});

it('enforces scopes with the spec error shape', function () {
    Passport::actingAs(User::factory()->create(), ['posts:read']);
    $post = Post::factory()->draft()->create(['content' => '<p>نص.</p>']);

    $error = blogToolError(callBlogTool('edit-post', [
        'post_id' => $post->id,
        'edits' => [['old' => 'نص', 'new' => 'كلام']],
    ]));

    expect($error['code'])->toBe('forbidden')
        ->and($error['message'])->toContain('`posts:write`')
        ->and($error['details']['action'])->toBe('edit-post')
        ->and($post->refresh()->content)->toBe('<p>نص.</p>');
});
