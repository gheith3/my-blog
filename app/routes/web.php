<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/posts/{slug}', 'post-show')->name('posts.show');
