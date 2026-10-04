<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <p class="font-semibold">Connect an AI agent</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Point an MCP client at this URL. It will ask you to approve access with the Connect flow. If the client cannot do that, create an API key below and send it as a Bearer token.
        </p>

        <div class="mt-3">
            <input
                type="text"
                readonly
                value="{{ url('/mcp/blog') }}"
                onclick="this.select()"
                class="w-full max-w-lg rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-800"
            />
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
