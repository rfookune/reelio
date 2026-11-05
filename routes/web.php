<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Discover')->name('home');

require __DIR__.'/settings.php';
