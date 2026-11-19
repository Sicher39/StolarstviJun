<?php

namespace App\Filament\Resources\CookieSettingResource\Pages;

use App\Filament\Resources\CookieSettingResource;
use App\Models\CookieSetting;
use Filament\Resources\Pages\CreateRecord;

/**
 * A new record starts from the wording the site already publishes, so an accidental removal of the only
 * record can be repaired without retyping the texts.
 */
class CreateCookieSetting extends CreateRecord
{
    protected static string $resource = CookieSettingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...CookieSetting::defaults(),
            ...$data,
        ];
    }
}
