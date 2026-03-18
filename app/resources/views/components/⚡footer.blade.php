<?php

use Livewire\Component;

new class extends Component
{
    //
}; ?>

<footer class="py-8 border-t border-gray-200 dark:border-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-gray-500">
                © {{ date('Y') }} Blog
            </p>
            <div class="flex items-center gap-4">
                <a href="#" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Twitter</a>
                <a href="#" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">GitHub</a>
            </div>
        </div>
    </div>
</footer>
