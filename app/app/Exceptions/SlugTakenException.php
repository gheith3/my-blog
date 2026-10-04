<?php

namespace App\Exceptions;

use RuntimeException;

class SlugTakenException extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $slug,
        public readonly ?int $postId,
    ) {
        $this->errorCode = 'slug_taken';

        parent::__construct("The slug \"{$slug}\" is already in use.");
    }

    /**
     * Spec-shaped error details (Blog MCP v2, section 9).
     *
     * @return array{slug: string, post_id: ?int}
     */
    public function details(): array
    {
        return [
            'slug' => $this->slug,
            'post_id' => $this->postId,
        ];
    }
}
