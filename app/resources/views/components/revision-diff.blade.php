{{-- Paragraph-level diff with word-level character highlights (Blog MCP v2, section 10).
     The Filament panel only ships its scoped fi-* CSS, so the diff colors come
     from the embedded stylesheet instead of Tailwind utilities. --}}
@once
    <style>
        .revision-diff { font-size: 0.875rem; line-height: 1.75; }
        .revision-diff p { padding: 0.25rem 0.75rem; border-radius: 0.375rem; }
        .revision-diff p + p { margin-top: 0.5rem; }
        .revision-diff .rrd-unchanged { color: rgb(156 163 175); }
        .revision-diff .rrd-removed { background-color: rgb(254 242 242); color: rgb(127 29 29); }
        .revision-diff .rrd-removed-paragraph { text-decoration: line-through; text-decoration-color: rgb(248 113 113); }
        .revision-diff .rrd-added { background-color: rgb(240 253 244); color: rgb(20 83 45); }
        .revision-diff del { background-color: rgb(254 202 202 / 0.7); border-radius: 0.125rem; text-decoration-color: rgb(239 68 68); }
        .revision-diff mark { background-color: rgb(187 247 208 / 0.7); color: inherit; border-radius: 0.125rem; }
        .dark .revision-diff .rrd-unchanged { color: rgb(107 114 128); }
        .dark .revision-diff .rrd-removed { background-color: rgb(69 10 10 / 0.4); color: rgb(254 202 202); }
        .dark .revision-diff .rrd-added { background-color: rgb(5 46 22 / 0.4); color: rgb(187 247 208); }
        .dark .revision-diff del { background-color: rgb(153 27 27 / 0.6); }
        .dark .revision-diff mark { background-color: rgb(22 101 52 / 0.6); }
    </style>
@endonce

<div dir="rtl" class="revision-diff">
    @foreach ($blocks as $block)
        @if ($block['type'] === 'unchanged')
            <p class="rrd-unchanged">{{ $block['text'] }}</p>
        @elseif ($block['type'] === 'removed')
            <p class="rrd-removed rrd-removed-paragraph">{{ $block['text'] }}</p>
        @elseif ($block['type'] === 'added')
            <p class="rrd-added">{{ $block['text'] }}</p>
        @else
            <p class="rrd-removed">
                @foreach ($block['oldSegments'] as $segment)
                    @if ($segment['type'] === 'removed')
                        <del>{{ $segment['text'] }}</del>
                    @else
                        {{ $segment['text'] }}
                    @endif
                @endforeach
            </p>
            <p class="rrd-added">
                @foreach ($block['newSegments'] as $segment)
                    @if ($segment['type'] === 'added')
                        <mark>{{ $segment['text'] }}</mark>
                    @else
                        {{ $segment['text'] }}
                    @endif
                @endforeach
            </p>
        @endif
    @endforeach
</div>
