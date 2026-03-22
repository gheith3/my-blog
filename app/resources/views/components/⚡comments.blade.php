<?php

use App\Models\Comment;
use App\Models\Post;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[\Livewire\Attributes\Layout('layouts.app')] class extends Component {
    public Post $post;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|string|max:5000')]
    public string $content = '';

    public function mount(Post $post): void
    {
        $this->post = $post;
    }

    public function submitComment(): void
    {
        $this->validate();

        $comment = new Comment([
            'name' => $this->name,
            'content' => $this->content,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_approved' => false,
        ]);

        $this->post->comments()->save($comment);

        $this->reset(['content']);
        $this->dispatch('comment-added');
    }

    public function getCommentsProperty()
    {
        return $this->post
            ->approvedComments()
            ->with(['approvedReplies'])
            ->get();
    }
};
?>

<div>
    <section class="py-12 border-t border-gray-200 dark:border-gray-800">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <h2 class="text-2xl font-semibold mb-8">
                {{ __('app.comments.title') }}
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $this->comments->count() }})
                </span>
            </h2>

            {{-- Comments List --}}
            <div class="space-y-8 mb-12">
                @forelse($this->comments as $comment)
                    <div class="border-b border-gray-200 dark:border-gray-800 pb-8 last:border-0">
                        {{-- Main Comment --}}
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div
                                    class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400 font-semibold">
                                    {{ strtoupper(substr($comment->name, 0, 1)) }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2">
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $comment->name }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">·</span>
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $comment->content }}
                                </p>
                            </div>
                        </div>

                        {{-- Replies --}}
                        @if ($comment->approvedReplies->count() > 0)
                            <div class="mt-6 ms-14 space-y-6">
                                @foreach ($comment->approvedReplies as $reply)
                                    <div class="flex gap-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-400 font-semibold text-sm">
                                                {{ strtoupper(substr($reply->name, 0, 1)) }}
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span
                                                    class="font-medium text-gray-900 dark:text-gray-100">{{ $reply->name }}</span>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">·</span>
                                                <span
                                                    class="text-sm text-gray-500 dark:text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-gray-700 dark:text-gray-300">
                                                {{ $reply->content }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                        {{ __('app.comments.no_comments') }}
                    </p>
                @endforelse
            </div>

            {{-- Add Comment Form --}}
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                <h3 class="text-lg font-medium mb-4">
                    {{ __('app.comments.add_comment') }}
                </h3>

                <form wire:submit="submitComment" class="space-y-4">
                    <div>
                        <label for="comment-name"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('app.comments.name') }} *
                        </label>
                        <input type="text" id="comment-name" wire:model="name"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                            placeholder="{{ __('app.comments.name_placeholder') }}">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="comment-content"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('app.comments.content') }} *
                        </label>
                        <textarea id="comment-content" wire:model="content" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"
                            placeholder="{{ __('app.comments.content_placeholder') }}"></textarea>
                        @error('content')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center px-6 py-2 bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-lg transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitComment">
                            {{ __('app.comments.submit') }}
                        </span>
                        <span wire:loading wire:target="submitComment">
                            {{ __('app.comments.submitting') }}
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>
