<?php

namespace App\Mcp\Resources;

use App\Settings\StyleGuideSettings;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The author's house style (Blog MCP v2, section 8). Read-only for agents:
 * only the author edits the document, in the dashboard.
 */
#[Description('The house writing style. Read it before any write to post content and follow it; when proofreading, change only errors, never word choice.')]
#[Uri('blog://style-guide')]
#[MimeType('text/markdown')]
class StyleGuide extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        return Response::text(app(StyleGuideSettings::class)->content);
    }
}
