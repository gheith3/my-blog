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

    @if (filled($newTokenPlaintext))
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 dark:border-amber-700 dark:bg-amber-950">
            <p class="font-semibold">Your new API key</p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Copy it now. It will not be shown again.</p>

            <div class="mt-4">
                @include('filament.pages.api-key-reveal', ['token' => $newTokenPlaintext, 'name' => $newTokenName])
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="dismissNewToken" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-300">
                    Done
                </button>
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
