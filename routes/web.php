<?php

use App\Http\Controllers\ConfiguratorController;
use App\Http\Controllers\ConfiguratorInquiryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomProductionController;
use App\Http\Controllers\DoorsController;
use App\Http\Controllers\ReferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware('vue')->name('front.')->group(function () {
    Route::inertia('/', 'Index')->name('index');
    Route::get('/dvere', DoorsController::class)->name('doors');
    Route::get('/konfigurator', ConfiguratorController::class)->name('configurator');
    Route::post('/konfigurator/poptavka', ConfiguratorInquiryController::class)->name('configurator.inquiry.store');
    Route::get('/zakazkova-vyroba', CustomProductionController::class)->name('customProduction');
    Route::get('/reference', ReferencesController::class)->name('references');
    Route::get('/kontakt', ContactController::class)->name('contact');
    Route::inertia('/ochrana-osobnich-udaju', 'Gdpr')->name('gdpr');
});
