<?php

use Livewire\Component;
use App\Settings\GeneralSettings;

new class extends Component
{
    //
}; ?>

@php($settings = app(GeneralSettings::class))
<section id="home" class="py-20 md:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
        {{-- Badge --}}
        <span class="inline-block px-3 py-1 text-sm text-gray-600 dark:text-gray-400 mb-6">
            {{ $settings->get('hero_welcome') }}
        </span>

        {{-- Main Heading --}}
        <h1 class="text-4xl md:text-5xl font-semibold mb-6 tracking-tight">
            {{ $settings->get('hero_title') }}
        </h1>

        {{-- Description --}}
        <p class="max-w-xl mx-auto text-lg text-gray-600 dark:text-gray-400 mb-8">
            {{ $settings->get('hero_description') }}
        </p>

        {{-- CTA --}}
        <a href="#posts" class="inline-block px-6 py-3 text-sm font-medium bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100">
            {{ $settings->get('hero_read_posts') }}
        </a>
    </div>
</section>
