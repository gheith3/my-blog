<?php

namespace App\Mcp\Tools\Revisions;

use App\Enums\RevisionState;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\PostRevision;
use App\Services\ContentViews;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read one revision: the full snapshot of title, content, excerpt, slug and status it stored. Add format for a content view and diff_against ("previous" or "live") for a paragraph-level text diff.')]
#[IsReadOnly]
class GetRevision extends Tool
{
    use BuildsBlogV2Responses;

    public function __construct(
        protected ContentViews $views,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'get-revision')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'revision_id' => ['required', 'string'],
            'format' => ['nullable', 'in:html,text,markdown,paragraphs'],
            'diff_against' => ['nullable', 'in:previous,live'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $revision = PostRevision::find($request->get('revision_id'));

        if ($revision === null) {
            return $this->notFound('revision', (string) $request->get('revision_id'));
        }

        $validated = $validator->validated();
        $format = $validated['format'] ?? 'html';

        $payload = [
            'revision_id' => $revision->id,
            'post_id' => $revision->post_id,
            'version' => $revision->version,
            'base_version' => $revision->base_version,
            'state' => $revision->state->value,
            'source' => $revision->source->value,
            'client_name' => $revision->client_name,
            'note' => $revision->note,
            'unguarded' => $revision->unguarded,
            'created_at' => $revision->created_at?->toIso8601String(),
            'decided_at' => $revision->decided_at?->toIso8601String(),
            'title' => $revision->title,
            'slug' => $revision->slug,
            'status' => $revision->status->value,
            'excerpt' => $revision->excerpt,
            'format' => $format,
            'content' => match ($format) {
                'text' => $this->views->toText($revision->content_html),
                'markdown' => $this->views->toMarkdown($revision->content_html),
                'paragraphs' => $this->views->toParagraphs($revision->content_html),
                default => $revision->content_html,
            },
        ];

        if (isset($validated['diff_against'])) {
            $targetHtml = $this->diffTarget($revision, $validated['diff_against']);

            $payload['diff_against'] = $validated['diff_against'];
            $payload['diff'] = $targetHtml === null
                ? null
                : $this->paragraphDiff($targetHtml, $revision->content_html);
        }

        return $this->payloadResponse($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'revision_id' => $schema->string()->required()->description('From list-revisions.'),
            'format' => $schema->string()->description('html (default), text, markdown, or paragraphs.'),
            'diff_against' => $schema->string()->description('"previous" (the revision before this one) or "live" (the post as it is now). Adds a paragraph-level text diff.'),
        ];
    }

    /**
     * The HTML to diff the snapshot against: the previous applied revision of
     * the same post, or the live post body. Null when there is no sensible
     * target (no previous revision, or the post is gone).
     */
    private function diffTarget(PostRevision $revision, string $against): ?string
    {
        if ($against === 'live') {
            return $revision->post()->withTrashed()->first()?->content;
        }

        $previous = PostRevision::where('post_id', $revision->post_id)
            ->where('state', RevisionState::Applied)
            ->where('id', '<', $revision->id)
            ->orderByDesc('id')
            ->first();

        return $previous?->content_html ?? '';
    }

    /**
     * Paragraph-level text diff: blocks aligned by index, one entry per block
     * whose text changed (added blocks have no `before`, removed blocks no
     * `after`).
     *
     * @return list<array{paragraph: int, before?: string, after?: string}>
     */
    private function paragraphDiff(string $beforeHtml, string $afterHtml): array
    {
        $before = array_column($this->views->toParagraphs($beforeHtml), 'text');
        $after = array_column($this->views->toParagraphs($afterHtml), 'text');
        $diff = [];

        for ($index = 0, $count = max(count($before), count($after)); $index < $count; $index++) {
            $beforeText = $before[$index] ?? null;
            $afterText = $after[$index] ?? null;

            if ($beforeText === $afterText) {
                continue;
            }

            $entry = ['paragraph' => $index + 1];

            if ($beforeText !== null) {
                $entry['before'] = $beforeText;
            }

            if ($afterText !== null) {
                $entry['after'] = $afterText;
            }

            $diff[] = $entry;
        }

        return $diff;
    }
}
