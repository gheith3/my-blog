<?php

use Livewire\Component;
use App\Settings\GeneralSettings;

new class extends Component {
    public bool $isOpen = false;

    public function toggleMenu(): void
    {
        $this->isOpen = !$this->isOpen;
    }
}; ?>

@php($settings = app(GeneralSettings::class))
<nav class="border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-14">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="font-semibold text-lg">
                {{ $settings->get('site_name') }}
            </a>

            {{-- Desktop Navigation --}}
            <div class="hidden md:flex items-center gap-6">
                <a href="{{ route('home') }}"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    {{ $settings->get('nav_home') }}
                </a>
                <a href="{{ route('home') }}#about"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    {{ $settings->get('nav_about') }}
                </a>
                <a href="{{ route('home') }}#posts"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    {{ $settings->get('nav_posts') }}
                </a>
                {{-- <livewire:theme-toggle /> --}}
                <livewire:language-switcher />
            </div>

            {{-- Mobile Menu Button --}}
            <div class="flex items-center gap-3 md:hidden">
                <livewire:theme-toggle />
                <livewire:language-switcher />
                <button wire:click="toggleMenu" class="p-2 text-gray-600 dark:text-gray-400">
                    <svg x-show="!$wire.isOpen" class="w-5 h-5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="$wire.isOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div x-show="$wire.isOpen" class="md:hidden border-t border-gray-200 dark:border-gray-800" style="display: none;">
        <div class="px-4 py-3 space-y-1">
            <a href="#home" wire:click="toggleMenu" class="block py-2 text-sm text-gray-600 dark:text-gray-400">
                {{ $settings->get('nav_home') }}
            </a>
            <a href="#about" wire:click="toggleMenu" class="block py-2 text-sm text-gray-600 dark:text-gray-400">
                {{ $settings->get('nav_about') }}
            </a>
            <a href="#posts" wire:click="toggleMenu" class="block py-2 text-sm text-gray-600 dark:text-gray-400">
                {{ $settings->get('nav_posts') }}
            </a>
        </div>
    </div>
</nav>
