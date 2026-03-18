<?php

use Livewire\Component;

new class extends Component
{
    public string $locale;

    public function mount(): void
    {
        $this->locale = app()->getLocale();
    }

    public function switchLanguage(string $locale): void
    {
        if (in_array($locale, ['en', 'ar'])) {
            session()->put('locale', $locale);
            $this->locale = $locale;
            $this->redirect(request()->header('Referer') ?? route('home'), navigate: true);
        }
    }
}; ?>

<div class="relative" x-data="{ open: false }">
    <button 
        x-on:click="open = !open"
        class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
        aria-label="{{ __('app.language.switch') }}"
    >
        <span class="text-sm font-medium">{{ $locale === 'ar' ? 'العربية' : 'EN' }}</span>
    </button>

    <div 
        x-show="open" 
        x-on:click.away="open = false"
        class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-lg"
        style="display: none;"
    >
        <button 
            wire:click="switchLanguage('en')"
            class="block w-full px-4 py-2 text-sm text-left hover:bg-gray-100 dark:hover:bg-gray-800 {{ $locale === 'en' ? 'bg-gray-100 dark:bg-gray-800 font-medium' : '' }}"
        >
            {{ __('app.language.en') }}
        </button>
        <button 
            wire:click="switchLanguage('ar')"
            class="block w-full px-4 py-2 text-sm text-left hover:bg-gray-100 dark:hover:bg-gray-800 {{ $locale === 'ar' ? 'bg-gray-100 dark:bg-gray-800 font-medium' : '' }}"
        >
            {{ __('app.language.ar') }}
        </button>
    </div>
</div>
