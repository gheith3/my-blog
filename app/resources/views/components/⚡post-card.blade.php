<?php

use Livewire\Component;

new class extends Component
{
    public $post;
}; ?>

<article class="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
    {{-- Content --}}
    <div class="p-6">
        {{-- Meta --}}
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-500 mb-3">
            <a href="{{ route('home', ['category' => $post->category?->id]) }}#posts" class="hover:underline">
                {{ $post->category?->name ?? 'Uncategorized' }}
            </a>
            <span>·</span>
            <span>{{ $post->published_at?->format('M j, Y') ?? $post->created_at->format('M j, Y') }}</span>
        </div>

        {{-- Title --}}
        <h3 class="text-xl font-medium mb-3">
            <a href="{{ route('posts.show', $post->slug) }}" class="hover:underline">
                {{ $post->title }}
            </a>
        </h3>

        {{-- Excerpt --}}
        <p class="text-gray-600 dark:text-gray-400 line-clamp-3 mb-4">
            {{ Str::limit(strip_tags($post->content), 160) }}
        </p>

        {{-- Tags --}}
        @if($post->tags->count() > 0)
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($post->tags as $tag)
                    <a href="{{ route('home', ['tag' => $tag->id]) }}#posts" class="text-sm text-gray-500 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:underline">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Read More --}}
        <a href="{{ route('posts.show', $post->slug) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline">
            Read more →
        </a>
    </div>
</article>
