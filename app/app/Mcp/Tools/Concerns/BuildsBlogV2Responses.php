<?php

namespace App\Mcp\Tools\Concerns;

use App\Exceptions\EditFailedException;
use App\Exceptions\SlugTakenException;
use App\Exceptions\VersionConflictException;
use App\Mcp\Support\ClientNameStore;
use App\Models\Post;
use App\Models\PostRevision;
use App\Services\ContentViews;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use stdClass;
use Throwable;

/**
 * Blog MCP v2 shared conventions (spec section 2): every v2 tool answers with
 * the same JSON payload — a write receipt on success, the single error shape
 * on failure. Responses use Response::structured(), so MCP clients that
 * understand structured content get the payload as structuredContent while
 * every client gets the same JSON (UTF-8, unescaped Unicode for Arabic) as
 * the text content. Errors additionally carry isError.
 */
trait BuildsBlogV2Responses
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function payloadResponse(array $payload): ResponseFactory
    {
        return Response::structured($payload);
    }

    /**
     * The one error shape from spec section 2: {error: {code, message, details}}.
     *
     * @param  array<string, mixed>  $details
     */
    protected function errorResponse(string $code, string $message, array $details = []): ResponseFactory
    {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
                // An empty details list must serialize as a JSON object, not [].
                'details' => $details === [] ? new stdClass : $details,
            ],
        ];

        return (new ResponseFactory(
            Response::error((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ))->withStructuredContent($payload);
    }

    protected function invalidParam(string $param, string $reason): ResponseFactory
    {
        return $this->errorResponse('invalid_param', "Invalid parameter `{$param}`: {$reason}", [
            'param' => $param,
            'reason' => $reason,
        ]);
    }

    protected function notFound(string $what, string|int $value): ResponseFactory
    {
        return $this->errorResponse('not_found', "No {$what} found for \"{$value}\".", [
            'what' => $what,
            'value' => $value,
        ]);
    }

    protected function payloadTooLarge(string $limit, int|string $actual): ResponseFactory
    {
        return $this->errorResponse('payload_too_large', "The request exceeds the limit of {$limit}.", [
            'limit' => $limit,
            'actual' => $actual,
        ]);
    }

    /**
     * Passport scope check answering with the spec error shape. The message
     * keeps the scope name so operators see exactly which scope is missing.
     */
    protected function ensureAbility(Request $request, string $ability, string $action): ?ResponseFactory
    {
        if ($request->user()->tokenCan($ability) || $request->user()->tokenCan('mcp:use')) {
            return null;
        }

        return $this->errorResponse(
            'forbidden',
            "This token's scopes don't include `{$ability}`.",
            ['action' => $action],
        );
    }

    /**
     * The write receipt from spec section 2. PostService write methods return
     * only the post, so the revision just written is the latest one.
     */
    protected function receipt(Post $post, ?PostRevision $revision, string $summary): array
    {
        return [
            'post_id' => $post->id,
            'version' => $post->version,
            'updated_at' => $post->updated_at?->toIso8601String(),
            'status' => $post->status->value,
            'revision_id' => $revision?->id,
            'summary' => $summary,
        ];
    }

    /**
     * The newest revision of a post (ULID ids sort by creation time).
     */
    protected function latestRevision(Post $post): ?PostRevision
    {
        return $post->revisions()->orderByDesc('id')->first();
    }

    /**
     * Map the phase-1 service exceptions onto the spec error catalogue
     * (section 9). Returns null for anything unrecognized.
     */
    protected function mapServiceException(Throwable $e, string $invalidParamName = 'edits'): ?ResponseFactory
    {
        if ($e instanceof VersionConflictException) {
            return $this->errorResponse('version_conflict', $e->getMessage(), $e->details());
        }

        if ($e instanceof SlugTakenException) {
            return $this->errorResponse('slug_taken', $e->getMessage(), $e->details());
        }

        if ($e instanceof EditFailedException) {
            $payload = $e->toError();

            return (new ResponseFactory(
                Response::error((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ))->withStructuredContent($payload);
        }

        if ($e instanceof InvalidArgumentException) {
            return $this->invalidParam($invalidParamName, $e->getMessage());
        }

        return null;
    }

    /**
     * The MCP client name stored on revisions. laravel/mcp v0.9 only exposes
     * clientInfo through the SessionInitialized event, so it is cached by
     * session id (see ClientNameStore) and falls back to "mcp".
     */
    protected function clientName(Request $request): string
    {
        return ClientNameStore::resolve($request->sessionId());
    }

    /**
     * The full post payload used by the reading tools and by verbose write
     * responses (spec sections 3, 5 and 7). `format` only changes what is
     * returned, never what is stored.
     *
     * @return array<string, mixed>
     */
    protected function postPayload(Post $post, string $format = 'html', bool $includeHtml = true, ?int $maxChars = null): array
    {
        $views = app(ContentViews::class);

        $payload = [
            'post_id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status->value,
            'version' => $post->version,
            'excerpt' => $post->excerpt,
            'category_id' => $post->category_id,
            'word_count' => $views->wordCount($post->content),
            'reading_time_minutes' => $views->readingTimeMinutes($post->content),
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'url' => $post->slug !== null && $post->slug !== '' ? route('posts.show', $post->slug) : null,
        ];

        if ($format === 'paragraphs') {
            $blocks = $views->toParagraphs($post->content);

            if (! $includeHtml) {
                $blocks = array_map(
                    fn (array $block): array => Arr::except($block, 'html'),
                    $blocks,
                );
            }

            return $payload + ['format' => 'paragraphs', 'blocks' => $blocks];
        }

        $content = match ($format) {
            'text' => $views->toText($post->content),
            'markdown' => $views->toMarkdown($post->content),
            default => $post->content,
        };

        if ($maxChars !== null && mb_strlen($content) > $maxChars) {
            $payload['truncated'] = true;
            $content = mb_substr($content, 0, $maxChars);
        }

        return $payload + ['format' => $format === 'html' ? 'html' : $format, 'content' => $content];
    }
}
