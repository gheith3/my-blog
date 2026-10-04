<?php

use App\Mcp\Servers\BlogServer;
use App\Mcp\Support\ClientNameStore;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Events\SessionInitialized;
use Laravel\Mcp\Facades\Mcp;

// laravel/mcp only exposes the MCP clientInfo at initialize time, through this
// event. It is cached by session id so write tools can stamp revisions with
// the real client name (Blog MCP v2, sections 2 and 6).
Event::listen(SessionInitialized::class, function (SessionInitialized $event): void {
    ClientNameStore::remember($event->sessionId, $event->clientInfo);
});

// Blog MCP server: AI agents manage posts and comments over MCP with the
// abilities their token grants. The "api" guard is Passport (config/auth.php)
// and accepts a static personal access token (the API Keys dashboard page) or
// a full OAuth 2.1 grant from the routes below, not session cookies, so this
// stays outside the "dashboard" Filament panel.
Mcp::web('/mcp/blog', BlogServer::class)
    ->middleware(['auth:api', 'throttle:mcp']);

// OAuth 2.1 "Connect" flow: discovery metadata and dynamic client
// registration (RFC 7591). An MCP client registers itself, so it never needs
// a pre-provisioned client_id.
Mcp::oauthRoutes();
