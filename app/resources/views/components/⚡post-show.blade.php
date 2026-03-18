<?php

use App\Models\Post;
use App\Enums\PostStatus;
use Livewire\Component;

new 
#[\Livewire\Attributes\Layout('layouts.app')]
class extends Component
{
    public Post $post;
    public $relatedPosts;

    public function mount(string $slug): void
    {
        $this->post = Post::where('slug', $slug)
            ->with(['category', 'user', 'tags'])
            ->firstOrFail();

        $this->loadRelatedPosts();
    }

    public function loadRelatedPosts(): void
    {
        $postIds = [$this->post->id];
        $tagIds = $this->post->tags->pluck('id')->toArray();

        if (! empty($tagIds)) {
            $this->relatedPosts = Post::query()
                ->select('posts.*')
                ->selectRaw('COUNT(DISTINCT tags.id) as shared_tags_count')
                ->joinRelationship('tags')
                ->where('posts.status', PostStatus::Published)
                ->whereIn('tags.id', $tagIds)
                ->whereNotIn('posts.id', $postIds)
                ->groupBy('posts.id')
                ->orderByDesc('shared_tags_count')
                ->orderByDesc('posts.published_at')
                ->limit(3)
                ->get();
        } else {
            $this->relatedPosts = Post::query()
                ->where('category_id', $this->post->category_id)
                ->where('status', PostStatus::Published)
                ->where('id', '!=', $this->post->id)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();
        }
    }

    public function getCategoryUrl(): string
    {
        if (! $this->post->category) {
            return route('home');
        }

        return route('home') . '?category=' . $this->post->category->id . '#posts';
    }

    public function getTagUrl(int $tagId): string
    {
        return route('home') . '?tag=' . $tagId . '#posts';
    }
}; ?>

<div>
    <livewire:navigation />

    <article class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            {{-- Back Link --}}
            <a href="{{ route('home') }}#posts" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 mb-8">
                ← Back to posts
            </a>

            {{-- Header --}}
            <header class="mb-10">
                {{-- Meta --}}
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 mb-4">
                    <a href="{{ $this->getCategoryUrl() }}" class="hover:underline">
                        {{ $post->category?->name ?? 'Uncategorized' }}
                    </a>
                    <span>·</span>
                    <span>{{ $post->published_at?->format('F j, Y') ?? $post->created_at->format('F j, Y') }}</span>
                    <span>·</span>
                    <span>{{ $post->user?->name ?? 'Anonymous' }}</span>
                </div>

                {{-- Title --}}
                <h1 class="text-3xl md:text-4xl font-semibold mb-6">
                    {{ $post->title }}
                </h1>

                {{-- Tags --}}
                @if($post->tags->count() > 0)
                    <div class="flex flex-wrap gap-3">
                        @foreach($post->tags as $tag)
                            <a href="{{ $this->getTagUrl($tag->id) }}" class="text-sm text-gray-500 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:underline">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </header>

            {{-- Content --}}
            <div class="prose prose-gray dark:prose-invert max-w-none">
                {!! $post->content !!}
            </div>

            {{-- Footer --}}
            <footer class="mt-16 pt-8 border-t border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">
                        Last updated {{ $post->updated_at->format('F j, Y') }}
                    </p>
                    
                    <div class="flex items-center gap-4">
                        <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(route('posts.show', $post->slug)) }}" 
                           target="_blank" 
                           class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            Share on Twitter
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    </article>

    {{-- Related Posts --}}
    @if($relatedPosts->count() > 0)
        <section class="py-16 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <h2 class="text-xl font-semibold mb-6">Related Posts</h2>
                <div class="grid gap-4">
                    @foreach($relatedPosts as $relatedPost)
                        <a href="{{ route('posts.show', $relatedPost->slug) }}" class="block p-4 border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-400 dark:hover:border-gray-600">
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 mb-2">
                                <span>{{ $relatedPost->category?->name ?? 'Uncategorized' }}</span>
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
