<?php

namespace App\Filament\Resources\CookieSettingResource\Pages;

use App\Filament\Resources\CookieSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditCookieSetting extends EditRecord
{
    protected static string $resource = CookieSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
