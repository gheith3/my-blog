<?php

namespace App\Mcp\Tools\Concerns;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Passport token scopes (posts:read/write, comments:read/write) are checked
 * on every tool, so a read-only API key can never reach a write tool even
 * though the MCP endpoint itself is authenticated.
 */
trait ChecksTokenAbility
{
    protected function ensureTokenAbility(Request $request, string $ability): ?Response
    {
        // `mcp:use` is laravel/mcp's generic scope, requested by clients that
        // connect through the OAuth "Connect" flow. The user approved that
        // consent screen, so it counts as full access. A personal access token
        // only carries the scopes chosen when it was minted.
        if ($request->user()->tokenCan($ability) || $request->user()->tokenCan('mcp:use')) {
            return null;
        }

        return Response::error("This token's scopes don't include `{$ability}`.");
    }
}
