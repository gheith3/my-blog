<?php

use Livewire\Component;
use App\Settings\GeneralSettings;

new class extends Component {
    //
}; ?>

@php($settings = app(GeneralSettings::class))
<section id="about" class="py-16 border-t border-gray-200 dark:border-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            {{-- Image --}}
            <div class="aspect-square bg-gray-100 dark:bg-gray-800 flex items-center justify-center overflow-hidden">
                @if ($settings->about_image)
                    <img src="{{ Storage::disk('public')->url($settings->about_image) }}"
                        alt="{{ $settings->get('about_title') }}" class="w-full h-full object-cover">
                @else
                    <svg class="w-20 h-20 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                @endif
            </div>

            {{-- Content --}}
            <div>
                <h2 class="text-2xl font-semibold mb-4">{{ $settings->get('about_title') }}</h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 leading-relaxed">
                    @if ($settings->get('about_description_1'))
                        <p>{{ $settings->get('about_description_1') }}</p>
                    @endif
                    @if ($settings->get('about_description_2'))
                        <p>{{ $settings->get('about_description_2') }}</p>
                    @endif
                    @if ($settings->get('about_description_3'))
                        <p>{{ $settings->get('about_description_3') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
