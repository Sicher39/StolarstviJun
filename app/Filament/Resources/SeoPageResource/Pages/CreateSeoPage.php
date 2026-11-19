<?php

namespace App\Filament\Resources\SeoPageResource\Pages;

use App\Filament\Resources\SeoPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeoPage extends CreateRecord
{
    protected static string $resource = SeoPageResource::class;

    /**
     * The external URL field exists only in the form: it is merged into `og_image` before the record
     * is created, so the column stays the single source of the sharing picture.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SeoPageResource::mergeExternalImageUrl($data);
    }
}
