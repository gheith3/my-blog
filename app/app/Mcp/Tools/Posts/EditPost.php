<?php

namespace App\Mcp\Tools\Posts;

use App\Enums\RevisionSource;
use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Models\Post;
use App\Services\ContentEditor;
use App\Services\PostService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Apply a list of exact find-and-replace edits to a post\'s body in one atomic call. Quote `old` exactly as it appears in the text view of get-post. Either every edit applies or none does. Use dry_run to preview, if_version to guard against overwriting a newer edit, and apply_to "pending" to stage the change for the author.')]
class EditPost extends Tool
{
    use BuildsBlogV2Responses;

    /**
     * Max edits per call (Blog MCP v2, section 10).
     */
    private const int MAX_EDITS = 200;

    public function __construct(
        protected ContentEditor $editor,
        protected PostService $posts,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:write', 'edit-post')) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'post_id' => ['required', 'integer'],
            'edits' => ['required', 'array', 'min:1'],
            'if_version' => ['nullable', 'integer', 'min:1'],
            'dry_run' => ['nullable', 'boolean'],
            'apply_to' => ['nullable', 'in:live,pending'],
            'whole_word' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->invalidParam((string) array_key_first($errors->toArray()), $errors->first());
        }

        $edits = $request->get('edits');

        if (count($edits) > self::MAX_EDITS) {
            return $this->payloadTooLarge('200 edits per call', count($edits));
        }

        $post = Post::find($request->get('post_id'));

        if ($post === null) {
            return $this->notFound('post', (int) $request->get('post_id'));
        }

        $validated = $validator->validated();
        $wholeWord = (bool) ($validated['whole_word'] ?? false);
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $applyTo = $validated['apply_to'] ?? 'live';
        $note = $validated['note'] ?? null;
        $ifVersion = isset($validated['if_version']) ? (int) $validated['if_version'] : null;

        try {
            // validate() both guards and locates every edit, so its diff also
            // tells us which paragraphs changed. apply() runs on the same
            // original text, so nothing here can half-apply.
            $diff = $this->editor->validate($post->content, $edits, $wholeWord);

            if ($dryRun) {
                return $this->payloadResponse([
                    'post_id' => $post->id,
                    'version' => $post->version,
                    'dry_run' => true,
                    'applied' => count($edits),
                    'diff' => $diff,
                ]);
            }

            $newHtml = $this->editor->apply($post->content, $edits, $wholeWord);
            $changedParagraphs = array_values(array_unique(array_column($diff, 'paragraph')));
            sort($changedParagraphs);
            $summary = count($edits).' edits applied in '.count($changedParagraphs).' paragraphs';
            $clientName = $this->clientName($request);

            if ($applyTo === 'pending') {
                $revision = $this->posts->stagePendingEdits($post, $newHtml, $edits, RevisionSource::Mcp, $clientName, $note);

                $this->logEdit('edit-post', $post, $clientName, count($edits), pending: true);

                return $this->payloadResponse(
                    $this->receipt($post->refresh(), $revision, $summary.' (staged for author approval)') + [
                        'pending' => true,
                        'applied' => count($edits),
                        'changed_paragraphs' => $changedParagraphs,
                    ],
                );
            }

            $post = $this->posts->saveEdits($post, $newHtml, RevisionSource::Mcp, $clientName, $note, $ifVersion, $edits);

            $this->logEdit('edit-post', $post, $clientName, count($edits));

            return $this->payloadResponse(
                $this->receipt($post, $this->latestRevision($post), $summary) + [
                    'applied' => count($edits),
                    'changed_paragraphs' => $changedParagraphs,
                ],
            );
        } catch (Throwable $e) {
            return $this->mapServiceException($e) ?? throw $e;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id, from list-posts.'),
            'edits' => $schema->array()
                ->items($schema->object([
                    'old' => $schema->string()->required()->description('Exact text to find, as it appears in the text view.'),
                    'new' => $schema->string()->required()->description('Replacement text; empty deletes `old`. Inserted as text, never markup.'),
                    'occurrence' => $schema->integer()->description('Which match to use when `old` appears more than once (1-based).'),
                    'paragraph' => $schema->integer()->description('Restrict the search to one block index (1-based).'),
                    'whole_word' => $schema->boolean()->description('Overrides the call-level setting for this edit.'),
                ]))
                ->required()
                ->description('1 to 200 find-and-replace edits, applied atomically.'),
            'if_version' => $schema->integer()->description('Fail with version_conflict if the post changed since you read it. Strongly advised.'),
            'dry_run' => $schema->boolean()->description('Validate and return the diff without saving anything.'),
            'apply_to' => $schema->string()->description('"live" (default) or "pending" to stage the change for author approval.'),
            'whole_word' => $schema->boolean()->description('Require word boundaries for every edit (Arabic combining marks and tatweel count as part of a word).'),
            'note' => $schema->string()->description('Stored on the revision, shown to the author. Max 200 chars.'),
        ];
    }

    /**
     * Spec section 10: log tool name, post id, version, client name and edit
     * count. Never log post bodies.
     */
    private function logEdit(string $tool, Post $post, string $clientName, int $editCount, bool $pending = false): void
    {
        Log::info('mcp.'.$tool, [
            'post_id' => $post->id,
            'version' => $post->version,
            'client_name' => $clientName,
            'edit_count' => $editCount,
            'pending' => $pending,
        ]);
    }
}
