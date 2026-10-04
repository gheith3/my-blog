<?php

use App\Mcp\Servers\BlogServer;
use Laravel\Mcp\Facades\Mcp;

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
