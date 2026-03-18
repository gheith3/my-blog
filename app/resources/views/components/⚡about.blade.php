<?php

use Livewire\Component;

new class extends Component
{
    //
}; ?>

<section id="about" class="py-16 border-t border-gray-200 dark:border-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            {{-- Image --}}
            <div class="aspect-square bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                <svg class="w-20 h-20 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>

            {{-- Content --}}
            <div>
                <h2 class="text-2xl font-semibold mb-4">{{ __('app.about.title') }}</h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 leading-relaxed">
                    @foreach(__('app.about.description') as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>

                {{-- Tags --}}
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach(__('app.about.tags') as $tag)
                        <span class="px-2 py-1 text-sm text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800">
                            {{ $tag }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
