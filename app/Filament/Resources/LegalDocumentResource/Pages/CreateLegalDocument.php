<?php

namespace App\Filament\Resources\LegalDocumentResource\Pages;

use App\Filament\Resources\LegalDocumentResource;
use App\Models\LegalDocument;
use Filament\Resources\Pages\CreateRecord;

/**
 * A new record is created as a draft: the public page keeps showing the baseline from
 * `config/legal.php` until the editor fills `published_at`.
 */
class CreateLegalDocument extends CreateRecord
{
    protected static string $resource = LegalDocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = $data['type'] ?? LegalDocument::TYPE_PRIVACY_POLICY;
        $data['locale'] = $data['locale'] ?? LegalDocument::DEFAULT_LOCALE;
        $data['published_at'] = $data['published_at'] ?? null;

        return $data;
    }
}
