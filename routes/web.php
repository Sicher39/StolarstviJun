<?php

use Illuminate\Support\Facades\Route;

Route::middleware('vue')->name('front.')->group(function () {
    Route::inertia('/', 'Index')->name('index');
    Route::inertia('/ochrana-osobnich-udaju', 'Gdpr')->name('gdpr');
});
