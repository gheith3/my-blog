<?php

use Livewire\Component;

new class extends Component
{
    // Theme handled by Alpine.js
}; ?>

<button 
    x-data="{ 
        theme: localStorage.getItem('theme') || 'dark',
        init() {
            if (this.theme === 'dark') {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        },
        toggle() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark'
            localStorage.setItem('theme', this.theme)
            if (this.theme === 'dark') {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        }
    }"
    x-on:click="toggle()"
    class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
    aria-label="Toggle theme"
>
    <svg x-show="theme === 'dark'" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
    <svg x-show="theme === 'light'" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
    </svg>
</button>
