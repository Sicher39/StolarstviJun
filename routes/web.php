<?php

use Illuminate\Support\Facades\Route;

Route::middleware('vue')->name('front.')->group(function () {
    Route::inertia('/', 'Index')->name('index');
    Route::inertia('/dvere', 'Doors')->name('doors');
    Route::inertia('/zakazkova-vyroba', 'CustomProduction')->name('customProduction');
    Route::inertia('/reference', 'References')->name('references');
    Route::inertia('/kontakt', 'Contact')->name('contact');
    Route::inertia('/ochrana-osobnich-udaju', 'Gdpr')->name('gdpr');
});
