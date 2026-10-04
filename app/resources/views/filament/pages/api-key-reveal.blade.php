@php
    $url = url('/mcp/blog');

    $snippet = json_encode([
        'mcpServers' => [
            'blog' => [
                'url' => $url,
                'headers' => ['Authorization' => 'Bearer '.$token],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp

<div class="flex flex-col gap-4">
    <div>
        <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $name }}</p>
        <input
            type="text"
            readonly
            value="{{ $token }}"
            onclick="this.select()"
            class="w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-800"
        />
    </div>

    <div>
        <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">MCP client config</p>
        <textarea
            readonly
            rows="8"
            onclick="this.select()"
            class="w-full resize-none rounded-md border-gray-300 font-mono text-xs dark:border-gray-700 dark:bg-gray-800"
        >{{ $snippet }}</textarea>
    </div>
</div>
