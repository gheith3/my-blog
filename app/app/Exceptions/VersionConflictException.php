<?php

namespace App\Exceptions;

use App\Models\Post;
use RuntimeException;

class VersionConflictException extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly Post $post,
        public readonly ?string $source,
    ) {
        $this->errorCode = 'version_conflict';

        parent::__construct(
            "Post {$post->id} is at version {$post->version}; the request expected a different version."
        );
    }

    /**
     * Spec-shaped error details (Blog MCP v2, section 9).
     *
     * @return array{current_version: int, updated_at: string, source: ?string}
     */
    public function details(): array
    {
        return [
            'current_version' => $this->post->version,
            'updated_at' => $this->post->updated_at?->toIso8601String(),
            'source' => $this->source,
        ];
    }
}
