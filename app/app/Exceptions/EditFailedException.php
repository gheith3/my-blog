<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when one or more edit-post edits cannot be applied. Nothing is
 * written: the failure list covers every failing edit, not just the first.
 */
final class EditFailedException extends Exception
{
    /**
     * @param  list<array<string, mixed>>  $failures  One entry per failing edit, with its 0-based index in the edits array,
     *                                                a code (edit_not_found, edit_ambiguous, edit_overlap or
     *                                                edit_spans_markup) and the matching spec fields.
     * @param  int  $editCount  Total number of edits in the failed call.
     */
    public function __construct(
        public readonly array $failures,
        public readonly int $editCount,
    ) {
        parent::__construct(
            count($failures).' of '.$editCount.' edits could not be applied. Nothing was saved.'
        );
    }

    /**
     * The spec error shape from Blog MCP v2 sections 2 and 4.
     *
     * @return array{error: array{code: string, message: string, details: array{failures: list<array<string, mixed>>}}}
     */
    public function toError(): array
    {
        return [
            'error' => [
                'code' => 'edit_failed',
                'message' => $this->getMessage(),
                'details' => ['failures' => $this->failures],
            ],
        ];
    }
}
