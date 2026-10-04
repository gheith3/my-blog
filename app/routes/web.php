<?php

use App\Http\Middleware\RedirectOldPostSlugs;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/posts/{slug}', 'post-show')->name('posts.show')->middleware(RedirectOldPostSlugs::class);
