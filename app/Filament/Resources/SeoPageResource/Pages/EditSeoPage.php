<?php

namespace App\Filament\Resources\SeoPageResource\Pages;

use App\Filament\Resources\SeoPageResource;
use App\Models\SeoPage;
use Filament\Resources\Pages\EditRecord;

/**
 * Editing is the only operation: there is deliberately no delete action, and SeoPage itself blocks
 * permanent deletion as well as bulk deletion of its records.
 */
class EditSeoPage extends EditRecord
{
    protected static string $resource = SeoPageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * An external URL can not be previewed by the upload field, so it is moved to the URL field and
     * the upload field is emptied. Both write into the same `og_image` column.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $ogImage = trim((string) ($data['og_image'] ?? ''));

        if (SeoPage::normalizeUrl($ogImage) !== null) {
            $data['og_image_external_url'] = $ogImage;
            $data['og_image'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SeoPageResource::mergeExternalImageUrl($data);
    }
}
