{{-- Pending revision review banner (Blog MCP v2, section 6.2). Rendered on EditPost via a schema View component. --}}
@foreach ($pendingRevisions as $revision)
    <x-filament::section
        icon="heroicon-o-clock"
        icon-color="warning"
    >
        <x-slot name="heading">
            {{ __('filament.resources.post.revisions.banner.heading', ['client' => $revision->client_name ?? __('filament.resources.post.revisions.banner.unknown_client')]) }}
        </x-slot>

        <x-slot name="description">
            {{ __('filament.resources.post.revisions.banner.created_at', ['date' => $revision->created_at->format('Y-m-d H:i')]) }}
            ·
            {{ __('filament.resources.post.revisions.banner.based_on', ['base' => $revision->base_version, 'current' => $post->version]) }}
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex gap-2">
                <x-filament::button
                    color="success"
                    size="sm"
                    wire:click="mountAction('approveRevision', { revision: '{{ $revision->getKey() }}' })"
                >
                    {{ __('filament.resources.post.revisions.actions.approve') }}
                </x-filament::button>
                <x-filament::button
                    color="danger"
                    size="sm"
                    outlined
                    wire:click="mountAction('rejectRevision', { revision: '{{ $revision->getKey() }}' })"
                >
                    {{ __('filament.resources.post.revisions.actions.reject') }}
                </x-filament::button>
            </div>
        </x-slot>

        @if (filled($revision->note))
            <p class="fi-section-content-note mb-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ $revision->note }}
            </p>
        @endif

        <x-revision-diff :live-html="$post->content" :pending-html="$revision->content_html" />
    </x-filament::section>
@endforeach
