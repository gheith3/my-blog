<?php

use App\Settings\GeneralSettings;

$settings = app(GeneralSettings::class);
?>
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $metaDescription ?? $settings->get('site_description') }}">

    <title>{{ $title ?? $settings->get('site_name') }}</title>


    <!-- Tab icon -->
    @if ($settings->about_image)
        <link rel="icon" href="{{ Storage::temporaryUrl($settings->about_image, now()->addMinutes(10)) }}"
            type="image/x-icon">
        <link rel="shortcut icon" href="{{ Storage::temporaryUrl($settings->about_image, now()->addMinutes(10)) }}"
            type="image/x-icon">
    @endif


    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if (app()->getLocale() === 'ar')
        <link
            href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">
    @endif

    {{-- Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles

    {{-- Dark mode script --}}
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>
</head>

<body class="antialiased bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <div class="min-h-screen flex flex-col">
        {{ $slot }}
    </div>

    @livewireScripts
</body>

</html>
