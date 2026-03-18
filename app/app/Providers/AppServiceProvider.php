<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Joaopaulolndev\FilamentEditProfile\Livewire\EditPasswordForm;
use Joaopaulolndev\FilamentEditProfile\Livewire\EditProfileForm;
use Joaopaulolndev\FilamentEditProfile\Livewire\BrowserSessionsForm;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         $url = env('APP_URL', 'http://localhost:5501');
        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }

        // Manually register FilamentEditProfile Livewire components
        Livewire::component('edit_password_form', EditPasswordForm::class);
        Livewire::component('edit_profile_form', EditProfileForm::class);
        Livewire::component('browser_sessions_form', BrowserSessionsForm::class);
    }
}
