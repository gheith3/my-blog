<?php

use App\Models\Post;
use App\Enums\PostStatus;
use Livewire\Component;
use Livewire\Attributes\Url;

new class extends Component
{
    #[Url(as: 'category')]
    public $selectedCategories = [];
    
    #[Url(as: 'tag')]
    public $selectedTags = [];
    
    #[Url]
    public string $search = '';
    
    public int $perPage = 6;

    public function mount(): void
    {
        if (is_string($this->selectedCategories)) {
            $this->selectedCategories = [$this->selectedCategories];
        }
        if (is_int($this->selectedCategories)) {
            $this->selectedCategories = [$this->selectedCategories];
        }
        if (empty($this->selectedCategories)) {
            $this->selectedCategories = [];
        }
        
        if (is_string($this->selectedTags)) {
            $this->selectedTags = [$this->selectedTags];
        }
        if (is_int($this->selectedTags)) {
            $this->selectedTags = [$this->selectedTags];
        }
        if (empty($this->selectedTags)) {
            $this->selectedTags = [];
        }
    }

    public function with(): array
    {
        $posts = Post::query()
            ->with(['category', 'tags'])
            ->where('status', PostStatus::Published)
            ->when($this->search, fn ($query) => $query->where('title', 'like', "%{$this->search}%"))
            ->when($this->selectedCategories, fn ($query) => $query->whereIn('category_id', $this->selectedCategories))
            ->when($this->selectedTags, function ($query) {
                $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->selectedTags));
            })
            ->orderByDesc('published_at')
            ->paginate($this->perPage);

        return [
            'posts' => $posts,
            'categories' => \App\Models\Category::all(),
        ];
    }

    public function toggleCategory(int $id): void
    {
        if (in_array($id, $this->selectedCategories)) {
            $this->selectedCategories = array_diff($this->selectedCategories, [$id]);
        } else {
            $this->selectedCategories[] = $id;
        }
    }

    public function toggleTag(int $id): void
    {
        if (in_array($id, $this->selectedTags)) {
            $this->selectedTags = array_diff($this->selectedTags, [$id]);
        } else {
            $this->selectedTags[] = $id;
        }
    }

    public function loadMore(): void
    {
        $this->perPage += 6;
    }
}; ?>

<section id="posts" class="py-16 border-t border-gray-200 dark:border-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        {{-- Header --}}
        <div class="mb-10">
            <h2 class="text-2xl font-semibold mb-2">{{ __('app.posts.title') }}</h2>
            <p class="text-gray-600 dark:text-gray-400">
                {{ __('app.posts.description') }}
            </p>
        </div>

        {{-- Active Filters Display --}}
        @if(count($selectedCategories) > 0 || count($selectedTags) > 0)
            <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-gray-500">{{ __('app.posts.filtered_by') }}</span>
                        @foreach($selectedCategories as $catId)
                            @php($cat = $categories->firstWhere('id', $catId))
                            @if($cat)
                                <span class="px-2 py-1 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    {{ $cat->getLocalizedName() }}
                                </span>
                            @endif
                        @endforeach
                        @foreach($selectedTags as $tagId)
                            @php($tag = \App\Models\Tag::find($tagId))
                            @if($tag)
                                <span class="px-2 py-1 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    #{{ $tag->getLocalizedName() }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                    <button 
                        wire:click="$set('selectedCategories', []); $set('selectedTags', [])"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 underline"
                    >
                        {{ __('app.posts.clear_filters') }}
                    </button>
                </div>
            </div>
        @endif

        {{-- Filters --}}
        <div class="mb-10 space-y-4">
            {{-- Search --}}
            <input 
                wire:model.live.debounce.300ms="search"
                type="text" 
                placeholder="{{ __('app.posts.search_placeholder') }}"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-gray-400 dark:focus:border-gray-600"
            >

            {{-- Categories --}}
            <div class="flex flex-wrap gap-2">
                @foreach($categories as $category)
                    <button
                        wire:click="toggleCategory({{ $category->id }})"
                        class="px-3 py-1 text-sm border {{ in_array($category->id, $selectedCategories) ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 border-gray-900 dark:border-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}"
                    >
                        {{ $category->getLocalizedName() }}
                    </button>
                @endforeach
            </div>

            @if(count($selectedCategories) > 0 || count($selectedTags) > 0)
                <button 
                    wire:click="$set('selectedCategories', []); $set('selectedTags', [])"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 underline"
                >
                    {{ __('app.posts.clear_filters') }}
                </button>
            @endif
        </div>

        {{-- Posts Grid --}}
        @if($posts->count() > 0)
            <div class="grid gap-6">
                @foreach($posts as $post)
                    <livewire:post-card :post="$post" :key="$post->id" />
                @endforeach
            </div>

            {{-- Load More --}}
            @if($posts->hasMorePages())
                <div class="mt-10 text-center">
                    <button 
                        wire:click="loadMore"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 text-sm font-medium border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50"
                    >
                        <span wire:loading.remove>{{ __('app.posts.load_more') }}</span>
                        <span wire:loading>{{ __('app.posts.loading') }}</span>
                    </button>
                </div>
            @endif
        @else
            <p class="text-center text-gray-500 py-12">{{ __('app.posts.no_posts') }}</p>
        @endif
    </div>
</section>
