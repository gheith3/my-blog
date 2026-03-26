<?php

use App\Models\Post;
use App\Enums\PostStatus;
use App\Settings\GeneralSettings;
use Livewire\Component;

new #[\Livewire\Attributes\Layout('layouts.app')] class extends Component {
    public Post $post;
    public $relatedPosts;

    public function mount(string $slug): void
    {
        $this->post = Post::where('slug', $slug)
            ->with(['category', 'user', 'tags'])
            ->firstOrFail();

        //to share draft posts with logged in users
        // if ($this->post->status != PostStatus::Published && !auth()->user()) {
        //     abort(404);
        // }

        $this->loadRelatedPosts();
    }

    public function loadRelatedPosts(): void
    {
        $postIds = [$this->post->id];
        $tagIds = $this->post->tags->pluck('id')->toArray();

        if (!empty($tagIds)) {
            $this->relatedPosts = Post::query()->select('posts.*')->selectRaw('COUNT(DISTINCT tags.id) as shared_tags_count')->joinRelationship('tags')->where('posts.status', PostStatus::Published)->whereIn('tags.id', $tagIds)->whereNotIn('posts.id', $postIds)->groupBy('posts.id')->orderByDesc('shared_tags_count')->orderByDesc('posts.published_at')->limit(3)->get();
        } else {
            $this->relatedPosts = Post::query()->where('category_id', $this->post->category_id)->where('status', PostStatus::Published)->where('id', '!=', $this->post->id)->orderByDesc('published_at')->limit(3)->get();
        }
    }

    public function getCategoryUrl(): string
    {
        if (!$this->post->category) {
            return route('home');
        }

        return route('home') . '?category=' . $this->post->category->id . '#posts';
    }

    public function getTagUrl(int $tagId): string
    {
        return route('home') . '?tag=' . $tagId . '#posts';
    }
}; ?>

@php
    $settings = app(GeneralSettings::class);
    // Set meta data for social sharing
    $title = $post->title;
    $metaDescription = strip_tags(substr($post->content, 0, 100)) . (strlen($post->content) > 100 ? '...' : '');
    $ogType = 'article';
    $ogUrl = route('posts.show', $post->slug);
    $ogImage = $post->thumbnail ? Storage::disk('public')->url($post->thumbnail) : null;
@endphp

@section('headMeta')
    {{-- Basic Meta --}}
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @php
        $keywords = collect();
        // Add words from title
        $keywords = $keywords->merge(explode(' ', $post->title));
        // Add category
        if ($post->category) {
            $keywords->push($post->category->getLocalizedName());
        }
        // Add tags
        foreach ($post->tags as $tag) {
            $keywords->push($tag->getLocalizedName());
        }
        $keywords = $keywords->map(fn($k) => trim($k))->filter()->unique()->values();
    @endphp
    <meta name="keywords" content="{{ $keywords->implode(', ') }}">
    <meta name="author" content="{{ $post->user?->name ?? 'Anonymous' }}">
    <link rel="canonical" href="{{ $ogUrl }}">
    <meta name="robots" content="index, follow">

    {{-- Tab icon --}}
    @if ($settings->about_image)
        <link rel="icon" href="{{ Storage::disk('public')->url($settings->about_image) }}" type="image/x-icon">
    @endif
    @if ($ogImage)
        <link rel="shortcut icon" href="{{ $ogImage }}" type="image/x-icon">
    @endif

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:site_name" content="{{ $settings->site_name ?? config('app.name') }}">
    <meta property="og:locale" content="{{ app()->getLocale() }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:alt" content="{{ $post->title }}">
    @endif
    @if ($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    @if ($post->updated_at)
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
    @endif
    @if ($post->category)
        <meta property="article:section" content="{{ $post->category->getLocalizedName() }}">
    @endif
    @foreach ($post->tags as $tag)
        <meta property="article:tag" content="{{ $tag->getLocalizedName() }}">
    @endforeach

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
        <meta name="twitter:image:alt" content="{{ $post->title }}">
    @endif

    {{-- JSON-LD Structured Data --}}
    @php
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $metaDescription,
            'url' => $ogUrl,
            'author' => [
                '@type' => 'Person',
                'name' => $post->user?->name ?? 'Anonymous',
            ],
            'keywords' => $post->tags->map(fn($tag) => $tag->getLocalizedName())->toArray(),
        ];
        if ($post->published_at) {
            $jsonLd['datePublished'] = $post->published_at->toIso8601String();
        }
        if ($post->updated_at) {
            $jsonLd['dateModified'] = $post->updated_at->toIso8601String();
        }
        if ($ogImage) {
            $jsonLd['image'] = $ogImage;
        }
        if ($post->category) {
            $jsonLd['articleSection'] = $post->category->getLocalizedName();
        }
    @endphp
    <script type="application/ld+json">@json($jsonLd)</script>
@endsection

<div>


    @if ($post->status != PostStatus::Published)
        <div class="bg-red-500 text-white py-2 px-4 text-2xl font-bold text-center">
            {{ __('app.posts.not_published') }}
        </div>
    @endif
    <livewire:navigation />

    <article class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            {{-- Back Link --}}
            <a href="{{ route('home') }}#posts"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 mb-8">
                {{ app()->getLocale() === 'ar' ? '→' : '←' }} {{ $settings->get('posts_back_to_posts') }}
            </a>

            {{-- Header --}}
            <header class="mb-10">
                {{-- Meta --}}
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 mb-4">
                    <a href="{{ $this->getCategoryUrl() }}" class="hover:underline">
                        {{ $post->category?->getLocalizedName() ?? $settings->get('posts_uncategorized') }}
                    </a>
                    <span>·</span>
                    <span>{{ $post->published_at?->format('F j, Y') ?? $post->created_at->format('F j, Y') }}</span>
                    <span>·</span>
                    <span>{{ $post->user?->name ?? 'Anonymous' }}</span>
                </div>

                {{-- Thumbnail --}}
                @if ($post->thumbnail)
                    <div class="mb-8">
                        <img src="{{ Storage::disk('public')->url($post->thumbnail) }}" alt="{{ $post->title }}"
                            class="w-full h-auto rounded-lg shadow-md">
                    </div>
                @endif


                {{-- Title --}}
                <h1 class="text-3xl md:text-4xl font-semibold mb-6">
                    {{ $post->title }}
                </h1>



                {{-- Tags --}}
                @if ($post->tags->count() > 0)
                    <div class="flex flex-wrap gap-3">
                        @foreach ($post->tags as $tag)
                            <a href="{{ $this->getTagUrl($tag->id) }}"
                                class="text-sm text-gray-500 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:underline">
                                #{{ $tag->getLocalizedName() }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </header>

            {{-- Content --}}
            <div class="prose prose-gray dark:prose-invert max-w-none text-xl">
                {!! $post->content !!}
            </div>

            {{-- Footer --}}
            <footer class="mt-16 pt-8 border-t border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">
                        {{ $settings->get('posts_last_updated') }} {{ $post->updated_at->format('F j, Y') }}
                    </p>

                    <div class="flex items-center gap-3">
                        {{-- Twitter/X --}}
                        <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(route('posts.show', $post->slug)) }}"
                            target="_blank"
                            class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors"
                            title="{{ $settings->get('posts_share_twitter') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path
                                    d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                            </svg>
                        </a>
                        {{-- WhatsApp --}}
                        <a href="https://wa.me/?text={{ urlencode($post->title . ' ' . route('posts.show', $post->slug)) }}"
                            target="_blank"
                            class="p-2 text-gray-500 hover:text-green-600 dark:hover:text-green-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors"
                            title="Share on WhatsApp">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path
                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.008-.57-.008-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                            </svg>
                        </a>
                        {{-- Copy Link --}}
                        <button type="button" x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText('{{ route('posts.show', $post->slug) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="p-2 text-gray-500 hover:text-blue-600 dark:hover:text-blue-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors cursor-pointer"
                            title="Copy Link">
                            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </footer>
        </div>
    </article>

    {{-- Comments Section --}}
    <livewire:comments :post="$post" />

    {{-- Related Posts --}}
    @if ($relatedPosts->count() > 0)
        <section class="py-16 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <h2 class="text-xl font-semibold mb-6">{{ $settings->get('posts_related_posts') }}</h2>
                <div class="grid gap-4">
                    @foreach ($relatedPosts as $relatedPost)
                        <a href="{{ route('posts.show', $relatedPost->slug) }}"
                            class="block p-4 border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-400 dark:hover:border-gray-600">
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 mb-2">
                                <span>{{ $relatedPost->category?->getLocalizedName() ?? $settings->get('posts_uncategorized') }}</span>
                                <span>·</span>
                                <span>{{ $relatedPost->published_at?->format('M j, Y') ?? $relatedPost->created_at->format('M j, Y') }}</span>
                            </div>
                            <h3 class="font-medium">{{ $relatedPost->title }}</h3>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif



    <livewire:footer />
</div>
