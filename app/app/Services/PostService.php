<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Exceptions\SlugTakenException;
use App\Exceptions\VersionConflictException;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\PostSlugRedirect;
use App\Models\User;
use Carbon\CarbonInterface;
use DOMDocument;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PostService
{
    /**
     * Number of revisions kept per post (Blog MCP v2, section 6).
     */
    public const REVISION_RETENTION = 100;

    /**
     * Fields updatePost() may write to the posts table.
     */
    private const UPDATABLE_FIELDS = [
        'title',
        'content',
        'excerpt',
        'slug',
        'status',
        'published_at',
        'thumbnail',
        'category_id',
    ];

    /**
     * Create a post and its first (applied) revision in one transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPost(
        array $data,
        RevisionSource $source,
        ?string $clientName = null,
        ?string $note = null,
        ?int $ifVersion = null,
    ): Post {
        return DB::transaction(function () use ($data, $source, $clientName, $note, $ifVersion) {
            $slug = $data['slug'] ?? null;
            if (is_string($slug) && $slug !== '') {
                $this->assertSlugFormat($slug);
                $this->assertSlugAvailable($slug);
            } else {
                $slug = null;
            }

            $content = (string) ($data['content'] ?? '');
            $status = $this->normalizeStatus($data['status'] ?? PostStatus::Draft);
            $publishedAt = $this->normalizeDate($data['published_at'] ?? null);
            [$status, $publishedAt] = $this->applyPublicationRules($status, $publishedAt);

            $excerpt = $data['excerpt'] ?? null;
            if (! is_string($excerpt) || trim($excerpt) === '') {
                $excerpt = $this->generateExcerpt($content);
            }

            $post = new Post;
            $post->fill([
                'user_id' => $data['user_id'],
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'content' => $content,
                'excerpt' => $excerpt,
                'status' => $status,
                'thumbnail' => $data['thumbnail'] ?? null,
                'published_at' => $publishedAt,
                'version' => 1,
            ]);
            $post->slug = $slug;
            $post->save();

            $this->writeRevision(
                $post,
                RevisionState::Applied,
                $source,
                $clientName,
                $note,
                unguarded: $ifVersion === null,
                version: 1,
            );

            return $post;
        });
    }

    /**
     * Update a post with compare-and-set on the version token.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws VersionConflictException
     * @throws SlugTakenException
     */
    public function updatePost(
        Post $post,
        array $data,
        RevisionSource $source,
        ?string $clientName = null,
        ?string $note = null,
        ?int $ifVersion = null,
        bool $unguarded = false,
    ): Post {
        return DB::transaction(function () use ($post, $data, $source, $clientName, $note, $ifVersion, $unguarded) {
            $post = $this->lockPost($post);
            $this->assertVersion($post, $ifVersion);

            foreach (self::UPDATABLE_FIELDS as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                if ($field === 'slug') {
                    $this->handleSlugChange($post, (string) $data['slug']);

                    continue;
                }

                if ($field === 'excerpt') {
                    $excerpt = $data['excerpt'];
                    $post->excerpt = is_string($excerpt) && trim($excerpt) !== ''
                        ? $excerpt
                        : $this->generateExcerpt((string) ($data['content'] ?? $post->content));

                    continue;
                }

                $post->{$field} = $data[$field];
            }

            [$status, $publishedAt] = $this->applyPublicationRules($post->status, $post->published_at);
            $post->status = $status;
            $post->published_at = $publishedAt;

            $post->version++;
            $post->save();

            $this->writeRevision(
                $post,
                RevisionState::Applied,
                $source,
                $clientName,
                $note,
                unguarded: $unguarded || $ifVersion === null,
                version: $post->version,
            );

            $this->pruneRevisions($post);

            return $post;
        });
    }

    /**
     * Save the result of an edit-post call (HTML produced by the edit engine).
     *
     * The original edits list is stored on the revision so a staged change can
     * be replayed on approval.
     *
     * @param  array<int, array<string, mixed>>|null  $edits
     *
     * @throws VersionConflictException
     */
    public function saveEdits(
        Post $post,
        string $newHtml,
        RevisionSource $source,
        ?string $clientName = null,
        ?string $note = null,
        ?int $ifVersion = null,
        ?array $edits = null,
    ): Post {
        return DB::transaction(function () use ($post, $newHtml, $source, $clientName, $note, $ifVersion, $edits) {
            $post = $this->lockPost($post);
            $this->assertVersion($post, $ifVersion);

            $post->content = $newHtml;

            if (! is_string($post->excerpt) || trim($post->excerpt) === '') {
                $post->excerpt = $this->generateExcerpt($newHtml);
            }

            [$status, $publishedAt] = $this->applyPublicationRules($post->status, $post->published_at);
            $post->status = $status;
            $post->published_at = $publishedAt;

            $post->version++;
            $post->save();

            $this->writeRevision(
                $post,
                RevisionState::Applied,
                $source,
                $clientName,
                $note,
                unguarded: $ifVersion === null,
                version: $post->version,
                edits: $edits,
            );

            $this->pruneRevisions($post);

            return $post;
        });
    }

    /**
     * Stage a title/content change for author approval without touching the live post.
     *
     * @param  array{title?: string, content?: string}  $data
     */
    public function stagePendingUpdate(
        Post $post,
        array $data,
        RevisionSource $source,
        ?string $clientName = null,
        ?string $note = null,
    ): PostRevision {
        return DB::transaction(function () use ($post, $data, $source, $clientName, $note) {
            $post = $this->lockPost($post);
            $this->supersedePendingFrom($post, $clientName);

            return $this->writeRevision(
                $post,
                RevisionState::Pending,
                $source,
                $clientName,
                $note,
                unguarded: false,
                version: null,
                baseVersion: $post->version,
                snapshot: [
                    'title' => $data['title'] ?? $post->title,
                    'content_html' => $data['content'] ?? $post->content,
                ],
            );
        });
    }

    /**
     * Stage an edit-post result for author approval without touching the live post.
     *
     * @param  array<int, array<string, mixed>>  $edits
     */
    public function stagePendingEdits(
        Post $post,
        string $newHtml,
        array $edits,
        RevisionSource $source,
        ?string $clientName = null,
        ?string $note = null,
    ): PostRevision {
        return DB::transaction(function () use ($post, $newHtml, $edits, $source, $clientName, $note) {
            $post = $this->lockPost($post);
            $this->supersedePendingFrom($post, $clientName);

            return $this->writeRevision(
                $post,
                RevisionState::Pending,
                $source,
                $clientName,
                $note,
                unguarded: false,
                version: null,
                baseVersion: $post->version,
                edits: $edits,
                snapshot: ['content_html' => $newHtml],
            );
        });
    }

    /**
     * Approve a pending revision. Applies the snapshot directly when the base
     * version is still current; otherwise replays the stored edits through
     * App\Services\ContentEditor (built separately). A whole-body staged update
     * or a failed replay marks the revision as conflict.
     */
    public function approveRevision(PostRevision $revision, User $decidedBy): PostRevision
    {
        return DB::transaction(function () use ($revision, $decidedBy) {
            $revision = PostRevision::lockForUpdate()->findOrFail($revision->getKey());

            if (! $revision->isPending()) {
                throw new InvalidArgumentException('Only pending revisions can be approved.');
            }

            $post = $this->lockPost($revision->post);
            $snapshotHtml = $revision->content_html;
            $applyFullSnapshot = $revision->base_version === $post->version;

            if (! $applyFullSnapshot) {
                $replayed = $this->replayEdits($post, $revision);

                if ($replayed === null) {
                    $revision->state = RevisionState::Conflict;
                    $revision->decided_at = now();
                    $revision->decided_by = $decidedBy->id;
                    $revision->save();

                    return $revision;
                }

                $snapshotHtml = $replayed;
            }

            if ($applyFullSnapshot) {
                $post->title = $revision->title;
                $post->excerpt = $revision->excerpt;
                $this->handleSlugChange($post, $revision->slug);

                [$status, $publishedAt] = $this->applyPublicationRules($revision->status, $post->published_at);
                $post->status = $status;
                $post->published_at = $publishedAt;
            }

            $post->content = $snapshotHtml;

            $post->version++;
            $post->save();

            $revision->state = RevisionState::Applied;
            $revision->version = $post->version;
            $revision->content_html = $snapshotHtml;
            $revision->decided_at = now();
            $revision->decided_by = $decidedBy->id;
            $revision->save();

            $this->pruneRevisions($post);

            return $revision;
        });
    }

    /**
     * Reject a pending revision; the live post stays untouched.
     */
    public function rejectRevision(PostRevision $revision, User $decidedBy): PostRevision
    {
        return DB::transaction(function () use ($revision, $decidedBy) {
            $revision = PostRevision::lockForUpdate()->findOrFail($revision->getKey());

            if (! $revision->isPending()) {
                throw new InvalidArgumentException('Only pending revisions can be rejected.');
            }

            $revision->state = RevisionState::Rejected;
            $revision->decided_at = now();
            $revision->decided_by = $decidedBy->id;
            $revision->save();

            return $revision;
        });
    }

    /**
     * Write a revision's snapshot back to the post. Creates a new applied
     * revision; history is never deleted.
     *
     * @throws VersionConflictException
     */
    public function restoreRevision(
        Post $post,
        PostRevision $revision,
        ?int $ifVersion = null,
        RevisionSource $source = RevisionSource::Dashboard,
        ?string $clientName = null,
        ?string $note = null,
    ): Post {
        return DB::transaction(function () use ($post, $revision, $ifVersion, $source, $clientName, $note) {
            $post = $this->lockPost($post);
            $this->assertVersion($post, $ifVersion);

            $post->title = $revision->title;
            $post->content = $revision->content_html;
            $post->excerpt = $revision->excerpt;
            $this->handleSlugChange($post, $revision->slug);
            $post->status = $revision->status;

            $post->version++;
            $post->save();

            $this->writeRevision(
                $post,
                RevisionState::Applied,
                $source,
                $clientName,
                $note ?? "Restored from revision {$revision->id}",
                unguarded: $ifVersion === null,
                version: $post->version,
            );

            $this->pruneRevisions($post);

            return $post;
        });
    }

    /**
     * Archive a post (reversible delete): status becomes archived, the row is
     * soft-deleted, and all revisions are kept.
     *
     * @throws VersionConflictException
     */
    public function archivePost(
        Post $post,
        ?int $ifVersion = null,
        RevisionSource $source = RevisionSource::Dashboard,
        ?string $clientName = null,
        ?string $note = null,
    ): Post {
        return DB::transaction(function () use ($post, $ifVersion, $source, $clientName, $note) {
            $post = $this->lockPost($post);
            $this->assertVersion($post, $ifVersion);

            $post->status = PostStatus::Archived;
            $post->version++;
            $post->save();

            $this->writeRevision(
                $post,
                RevisionState::Applied,
                $source,
                $clientName,
                $note,
                unguarded: $ifVersion === null,
                version: $post->version,
            );

            $post->delete();

            $this->pruneRevisions($post);

            return $post;
        });
    }

    /**
     * Permanently delete a post. Requires a matching if_version.
     *
     * @throws VersionConflictException
     */
    public function deletePostPermanently(Post $post, int $ifVersion): void
    {
        DB::transaction(function () use ($post, $ifVersion) {
            $post = $this->lockPost($post);
            $this->assertVersion($post, $ifVersion);

            $post->forceDelete();
        });
    }

    /**
     * Generate an excerpt from the first paragraph of an HTML body: plain text,
     * cut at a word boundary, max $maxLength characters, ending with "…" when
     * truncated.
     */
    public function generateExcerpt(string $html, int $maxLength = 300): string
    {
        $text = $this->firstParagraphText($html);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut)."\u{2026}";
    }

    /**
     * Lock the post row for update inside the current transaction.
     */
    private function lockPost(Post $post): Post
    {
        /** @var Post $locked */
        $locked = Post::withTrashed()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();

        return $locked;
    }

    /**
     * Compare-and-set guard on the version token.
     *
     * @throws VersionConflictException
     */
    private function assertVersion(Post $post, ?int $ifVersion): void
    {
        if ($ifVersion !== null && $ifVersion !== $post->version) {
            throw new VersionConflictException($post, $this->latestWriteSource($post));
        }
    }

    private function latestWriteSource(Post $post): ?string
    {
        $source = PostRevision::where('post_id', $post->id)
            ->where('state', RevisionState::Applied)
            ->latest('created_at')
            ->value('source');

        return $source instanceof RevisionSource ? $source->value : $source;
    }

    /**
     * Write one revision row with a full snapshot of the post.
     *
     * @param  array<int, array<string, mixed>>|null  $edits
     * @param  array<string, mixed>  $snapshot  Snapshot field overrides (pending revisions stage a would-be state)
     */
    private function writeRevision(
        Post $post,
        RevisionState $state,
        RevisionSource $source,
        ?string $clientName,
        ?string $note,
        bool $unguarded,
        ?int $version,
        ?int $baseVersion = null,
        ?array $edits = null,
        array $snapshot = [],
    ): PostRevision {
        $revision = new PostRevision;
        $revision->fill([
            'post_id' => $post->id,
            'version' => $version,
            'base_version' => $baseVersion,
            'state' => $state,
            'source' => $source,
            'client_name' => $clientName,
            'note' => $note,
            'unguarded' => $unguarded,
            'title' => $snapshot['title'] ?? $post->title,
            'content_html' => $snapshot['content_html'] ?? $post->content,
            'excerpt' => $snapshot['excerpt'] ?? $post->excerpt,
            'slug' => $snapshot['slug'] ?? $post->slug,
            'status' => $snapshot['status'] ?? $post->status,
            'edits' => $edits,
        ]);
        $revision->save();

        return $revision;
    }

    /**
     * Mark previous pending revisions from the same client on this post as superseded.
     */
    private function supersedePendingFrom(Post $post, ?string $clientName): void
    {
        PostRevision::where('post_id', $post->id)
            ->where('state', RevisionState::Pending)
            ->where(fn ($query) => $clientName === null
                ? $query->whereNull('client_name')
                : $query->where('client_name', $clientName))
            ->update(['state' => RevisionState::Superseded]);
    }

    /**
     * Replay stored edit-post edits on the current body via the content editor
     * (App\Services\ContentEditor, provided by the edit engine). Returns the
     * resulting HTML, or null when replay is impossible.
     *
     * @param  array<int, array<string, mixed>>|null  $edits
     */
    private function replayEdits(Post $post, PostRevision $revision): ?string
    {
        if ($revision->edits === null) {
            return null;
        }

        $editorClass = 'App\\Services\\ContentEditor';

        if (! class_exists($editorClass)) {
            return null;
        }

        try {
            /** @var string $result */
            $result = app($editorClass)->apply($post->content, $revision->edits);

            return $result;
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeStatus(mixed $status): PostStatus
    {
        if ($status instanceof PostStatus) {
            return $status;
        }

        return PostStatus::from((string) $status);
    }

    private function normalizeDate(mixed $date): ?CarbonInterface
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return Date::parse((string) $date);
    }

    /**
     * Publication rules (Blog MCP v2, section 5): a future published_at moves a
     * published post to scheduled; publishing with no date stamps the current
     * time; a scheduled post whose date has passed becomes published.
     *
     * @return array{0: PostStatus, 1: ?CarbonInterface}
     */
    private function applyPublicationRules(PostStatus $status, mixed $publishedAt): array
    {
        $publishedAt = $this->normalizeDate($publishedAt);

        if ($status === PostStatus::Published && $publishedAt === null) {
            return [PostStatus::Published, now()];
        }

        if ($status === PostStatus::Published && $publishedAt->isFuture()) {
            return [PostStatus::Scheduled, $publishedAt];
        }

        if ($status === PostStatus::Scheduled && $publishedAt !== null && ! $publishedAt->isFuture()) {
            return [PostStatus::Published, $publishedAt];
        }

        return [$status, $publishedAt];
    }

    /**
     * @throws SlugTakenException
     */
    private function handleSlugChange(Post $post, string $newSlug): void
    {
        if ($newSlug === '' || $newSlug === $post->slug) {
            return;
        }

        $this->assertSlugFormat($newSlug);
        $this->assertSlugAvailable($newSlug, $post->id);

        if ($post->status === PostStatus::Published) {
            PostSlugRedirect::firstOrCreate(
                ['old_slug' => $post->slug],
                ['post_id' => $post->id],
            );
        }

        $post->slug = $newSlug;
    }

    private function assertSlugFormat(string $slug): void
    {
        if (! preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            throw new InvalidArgumentException(
                "The slug \"{$slug}\" is invalid: use lowercase latin letters, digits and hyphens, max 80 characters."
            );
        }
    }

    /**
     * A slug is taken when another post (including soft-deleted ones) uses it,
     * or when a redirect already points it at another post.
     *
     * @throws SlugTakenException
     */
    private function assertSlugAvailable(string $slug, ?int $ignorePostId = null): void
    {
        $existing = Post::withTrashed()
            ->where('slug', $slug)
            ->when($ignorePostId !== null, fn ($query) => $query->where('id', '!=', $ignorePostId))
            ->first();

        if ($existing !== null) {
            throw new SlugTakenException($slug, $existing->id);
        }

        $redirect = PostSlugRedirect::where('old_slug', $slug)->first();

        if ($redirect !== null) {
            if ($redirect->post_id === $ignorePostId) {
                $redirect->delete();

                return;
            }

            throw new SlugTakenException($slug, $redirect->post_id);
        }
    }

    /**
     * Keep at most REVISION_RETENTION revisions per post; never prune pending
     * revisions or the first published revision.
     */
    private function pruneRevisions(Post $post): void
    {
        $firstPublishedId = PostRevision::where('post_id', $post->id)
            ->where('state', RevisionState::Applied)
            ->where('status', PostStatus::Published)
            ->orderBy('created_at')
            ->orderBy('version')
            ->orderBy('id')
            ->value('id');

        $prunable = PostRevision::where('post_id', $post->id)
            ->where('state', '!=', RevisionState::Pending)
            ->when($firstPublishedId !== null, fn ($query) => $query->where('id', '!=', $firstPublishedId))
            ->orderByDesc('created_at')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->pluck('id');

        if ($prunable->count() <= self::REVISION_RETENTION) {
            return;
        }

        PostRevision::whereIn('id', $prunable->slice(self::REVISION_RETENTION)->all())->delete();
    }

    /**
     * Extract the decoded plain text of the first paragraph of an HTML body.
     */
    private function firstParagraphText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $paragraphs = $document->getElementsByTagName('p');
        $text = $paragraphs->length > 0
            ? (string) $paragraphs->item(0)->textContent
            : (string) $document->textContent;

        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
