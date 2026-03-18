<?php

use Livewire\Component;
use App\Settings\GeneralSettings;

new class extends Component
{
    //
}; ?>

@php($settings = app(GeneralSettings::class))
<footer class="py-8 border-t border-gray-200 dark:border-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-gray-500">
                © {{ date('Y') }} {{ $settings->get('site_name') }}. {{ $settings->get('footer_copyright') }}
            </p>
            <div class="flex items-center gap-4">
                @if($settings->twitter_url)
                    <a href="{{ $settings->twitter_url }}" target="_blank" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Twitter</a>
                @endif
                @if($settings->github_url)
                    <a href="{{ $settings->github_url }}" target="_blank" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">GitHub</a>
                @endif
                @if($settings->linkedin_url)
                    <a href="{{ $settings->linkedin_url }}" target="_blank" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">LinkedIn</a>
                @endif
            </div>
        </div>
    </div>
</footer>
