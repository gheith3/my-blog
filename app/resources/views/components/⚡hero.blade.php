<?php

use Livewire\Component;

new class extends Component
{
    //
}; ?>

<section id="home" class="py-20 md:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
        {{-- Badge --}}
        <span class="inline-block px-3 py-1 text-sm text-gray-600 dark:text-gray-400 mb-6">
            {{ __('app.hero.welcome') }}
        </span>

        {{-- Main Heading --}}
        <h1 class="text-4xl md:text-5xl font-semibold mb-6 tracking-tight">
            {{ __('app.hero.title') }}
        </h1>

        {{-- Description --}}
        <p class="max-w-xl mx-auto text-lg text-gray-600 dark:text-gray-400 mb-8">
            {{ __('app.hero.description') }}
        </p>

        {{-- CTA --}}
        <a href="#posts" class="inline-block px-6 py-3 text-sm font-medium bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100">
            {{ __('app.hero.read_posts') }}
        </a>
    </div>
</section>
