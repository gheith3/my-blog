<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Joaopaulolndev\FilamentEditProfile\Livewire\BrowserSessionsForm;
use Joaopaulolndev\FilamentEditProfile\Livewire\EditPasswordForm;
use Joaopaulolndev\FilamentEditProfile\Livewire\EditProfileForm;
use Laravel\Passport\Passport;
use Livewire\Livewire;

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

        // MCP server (routes/ai.php): a finite per-token budget, keyed by
        // token id (falls back to IP before auth resolves).
        RateLimiter::for('mcp', fn ($request) => Limit::perMinute(120)
            ->by($request->user()?->currentAccessToken()?->id ?? $request->ip()));

        // Real scopes (not just laravel/mcp's generic "mcp:use") so the OAuth
        // consent screen shows what is being granted, and personal access
        // tokens from the API Keys page can request them by name.
        Passport::tokensCan([
            'posts:read' => 'List and read posts, categories, and tags',
            'posts:write' => 'Create, edit, and delete posts',
            'comments:read' => 'List and read comments',
            'comments:write' => 'Create, edit, approve, and delete comments',
        ]);

        // Passport ships no default consent screen; this one is styled to
        // match the Filament dashboard (resources/views/oauth/authorize.blade.php).
        Passport::authorizationView('oauth.authorize');

        // Manually register FilamentEditProfile Livewire components
        Livewire::component('edit_password_form', EditPasswordForm::class);
        Livewire::component('edit_profile_form', EditProfileForm::class);
        Livewire::component('browser_sessions_form', BrowserSessionsForm::class);
    }
}
