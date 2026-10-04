{{-- Revision details modal (Blog MCP v2, section 6): full metadata, the stored
     snapshot with the complete post content, staged edit list, and — for
     pending revisions — a diff against the live post. Uses a scoped stylesheet
     because the Filament panel only ships its fi-* CSS. --}}
@once
    <style>
        .revision-details { font-size: 0.875rem; line-height: 1.6; }
        .revision-details h3 { font-weight: 600; margin: 1.25rem 0 0.5rem; }
        .revision-details h3:first-child { margin-top: 0; }
        .revision-details dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.375rem 1rem; }
        .revision-details dt { color: rgb(107 114 128); }
        .revision-details dd { margin: 0; overflow-wrap: anywhere; }
        .revision-details .rvd-content { max-height: 24rem; overflow-y: auto; padding: 0.75rem 1rem; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; }
        .revision-details .rvd-edit { padding: 0.375rem 0.75rem; border-radius: 0.375rem; }
        .revision-details .rvd-edit + .rvd-edit { margin-top: 0.375rem; }
        .revision-details .rvd-old { background-color: rgb(254 242 242); color: rgb(127 29 29); }
        .revision-details .rvd-new { background-color: rgb(240 253 244); color: rgb(20 83 45); }
        .dark .revision-details dt { color: rgb(156 163 175); }
        .dark .revision-details .rvd-content { border-color: rgb(55 65 81); }
        .dark .revision-details .rvd-old { background-color: rgb(69 10 10 / 0.4); color: rgb(254 202 202); }
        .dark .revision-details .rvd-new { background-color: rgb(5 46 22 / 0.4); color: rgb(187 247 208); }
    </style>
@endonce

<div class="revision-details">
    <h3>{{ __('filament.resources.post.revisions.details.meta') }}</h3>
    <dl>
        <dt>{{ __('filament.resources.post.revisions.details.revision_id') }}</dt>
        <dd>{{ $revision->id }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.state') }}</dt>
        <dd><x-filament::badge :color="$revision->state->getColor()" :icon="$revision->state->getIcon()">{{ $revision->state->getLabel() }}</x-filament::badge></dd>

        <dt>{{ __('filament.resources.post.revisions.fields.source') }}</dt>
        <dd><x-filament::badge :color="$revision->source->getColor()">{{ $revision->source->getLabel() }}</x-filament::badge></dd>

        <dt>{{ __('filament.resources.post.revisions.fields.client_name') }}</dt>
        <dd>{{ $revision->client_name ?? '-' }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.note') }}</dt>
        <dd>{{ $revision->note ?? '-' }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.unguarded') }}</dt>
        <dd>{{ $revision->unguarded ? __('filament.resources.post.revisions.details.yes') : __('filament.resources.post.revisions.details.no') }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.version') }}</dt>
        <dd>{{ $revision->version ?? '-' }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.base_version') }}</dt>
        <dd>{{ $revision->base_version ?? '-' }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.created_at') }}</dt>
        <dd>{{ $revision->created_at?->toDateTimeString() }}</dd>

        <dt>{{ __('filament.resources.post.revisions.fields.decided_at') }}</dt>
        <dd>{{ $revision->decided_at?->toDateTimeString() ?? '-' }}</dd>

        <dt>{{ __('filament.resources.post.revisions.details.decided_by') }}</dt>
        <dd>{{ $revision->decider?->name ?? '-' }}</dd>
    </dl>

    <h3>{{ __('filament.resources.post.revisions.details.snapshot') }}</h3>
    <dl>
        <dt>{{ __('filament.resources.post.revisions.details.title') }}</dt>
        <dd>{{ $revision->title }}</dd>

        <dt>{{ __('filament.resources.post.revisions.details.slug') }}</dt>
        <dd>{{ $revision->slug }}</dd>

        <dt>{{ __('filament.resources.post.revisions.details.status') }}</dt>
        <dd><x-filament::badge :color="$revision->status->getColor()">{{ $revision->status->getLabel() }}</x-filament::badge></dd>

        <dt>{{ __('filament.resources.post.revisions.details.excerpt') }}</dt>
        <dd>{{ $revision->excerpt ?? '-' }}</dd>
    </dl>

    @if ($revision->isPending())
        <h3>{{ __('filament.resources.post.revisions.details.diff_from_live') }}</h3>
        <x-revision-diff :live-html="$post->content" :pending-html="$revision->content_html" />
    @endif

    @if (! empty($revision->edits))
        <h3>{{ __('filament.resources.post.revisions.details.edits') }}</h3>
        <div dir="rtl">
            @foreach ($revision->edits as $edit)
                <div class="rvd-edit">
                    <span class="rvd-old">{{ $edit['old'] ?? '' }}</span>
                    ←
                    <span class="rvd-new">{{ $edit['new'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <h3>{{ __('filament.resources.post.revisions.details.content') }}</h3>
    <div dir="rtl" class="rvd-content">{!! $revision->content_html !!}</div>
</div>
