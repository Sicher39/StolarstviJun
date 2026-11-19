<?php

namespace App\Filament\Resources\CookieSettingResource\Pages;

use App\Filament\Resources\CookieSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCookieSettings extends ListRecords
{
    protected static string $resource = CookieSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Creating a second record would confuse the dialog, which always reads the first one.
            CreateAction::make()
                ->visible(fn (): bool => CookieSettingResource::canCreate()),
        ];
    }
}
