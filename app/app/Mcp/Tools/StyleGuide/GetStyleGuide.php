<?php

namespace App\Mcp\Tools\StyleGuide;

use App\Mcp\Tools\Concerns\BuildsBlogV2Responses;
use App\Settings\StyleGuideSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the house writing style (content, version, updated_at). Same document as the blog://style-guide resource, for clients without resource support. Read it before any write to post content and follow it. Read-only: only the author edits it, in the dashboard.')]
#[IsReadOnly]
class GetStyleGuide extends Tool
{
    use BuildsBlogV2Responses;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->ensureAbility($request, 'posts:read', 'get-style-guide')) {
            return $error;
        }

        $settings = app(StyleGuideSettings::class);

        return $this->payloadResponse([
            'content' => $settings->content,
            'version' => $settings->version,
            'updated_at' => $settings->updated_at,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
