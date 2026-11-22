<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Compliance\ConsentController;
use App\Http\Controllers\Compliance\CookieConfigController;
use App\Http\Controllers\ConfiguratorController;
use App\Http\Controllers\ConfiguratorInquiryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookiePolicyController;
use App\Http\Controllers\CustomProductionController;
use App\Http\Controllers\DoorsController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\ReferencesController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware('vue')->name('front.')->group(function () {
    Route::inertia('/', 'Index')->name('index');
    Route::get('/dvere', DoorsController::class)->name('doors');
    Route::get('/konfigurator', ConfiguratorController::class)->name('configurator');
    Route::post('/konfigurator/poptavka', ConfiguratorInquiryController::class)->name('configurator.inquiry.store');
    Route::get('/zakazkova-vyroba', CustomProductionController::class)->name('customProduction');
    Route::get('/reference', ReferencesController::class)->name('references');
    Route::get('/katalog', CatalogController::class)->name('catalog');
    Route::get('/kontakt', ContactController::class)->name('contact');
    Route::get('/ochrana-osobnich-udaju', LegalDocumentController::class)->name('gdpr');
    // The cookie policy has its own page; the privacy page keeps publishing only the privacy policy.
    Route::get('/zasady-cookies', CookiePolicyController::class)->name('cookies');
});

// Both files are derived from config('app.url'), so no environment specific URL is hardcoded in
// the repository. They live outside the Inertia group, because they are plain text responses.
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');
Route::get('/robots.txt', RobotsController::class)->name('seo.robots');

// The JSON API of the cookie dialog. Neither endpoint renders an Inertia page, so they live outside the
// Inertia group as well; the configuration is read on every page load, the consent is written when the
// visitor chooses. The throttle keeps a broken loop of a client from flooding the evidence table.
Route::get('/api/compliance/cookie-config', CookieConfigController::class)
    ->name('api.compliance.cookie-config');

Route::post('/api/compliance/consent', ConsentController::class)
    ->middleware('throttle:60,1')
    ->name('api.compliance.consent');
