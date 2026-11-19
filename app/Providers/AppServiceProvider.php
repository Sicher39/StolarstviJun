<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

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
        // The reference image upload converts photos to WebP in the admin's browser before Filament
        // hands them to Livewire, so the script has to be part of the panel markup. It is a plain file
        // in `public/js/admin`, therefore no frontend build step is required for the admin panel.
        FilamentAsset::register([
            Js::make('reference-image-webp', asset('js/admin/reference-image-webp.js')),
        ]);
    }
}
