<?php

namespace App\Mcp\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Remembers the MCP client identity announced at `initialize` time.
 *
 * laravel/mcp does not expose the client info on the per-call Request object;
 * it is only carried by the SessionInitialized event (registered in
 * routes/ai.php), keyed by the MCP session id that later requests send back
 * in the MCP-Session-Id header. Anything without a known session falls back
 * to "mcp" as the revision client name.
 */
final class ClientNameStore
{
    private const int TTL_SECONDS = 86400;

    private const string KEY_PREFIX = 'mcp-client-name:';

    /**
     * @param  array{name?: string, title?: string, version?: string}|null  $clientInfo
     */
    public static function remember(string $sessionId, ?array $clientInfo): void
    {
        $name = $clientInfo['title'] ?? $clientInfo['name'] ?? null;

        if ($sessionId !== '' && is_string($name) && $name !== '') {
            Cache::put(self::KEY_PREFIX.$sessionId, $name, self::TTL_SECONDS);
        }
    }

    public static function resolve(?string $sessionId): string
    {
        if ($sessionId === null || $sessionId === '') {
            return 'mcp';
        }

        $name = Cache::get(self::KEY_PREFIX.$sessionId);

        return is_string($name) && $name !== '' ? $name : 'mcp';
    }
}
