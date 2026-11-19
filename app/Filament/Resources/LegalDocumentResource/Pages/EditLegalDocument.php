<?php

namespace App\Filament\Resources\LegalDocumentResource\Pages;

use App\Filament\Resources\LegalDocumentResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Editing is the only operation on a document: the header holds no delete action, so a text that
 * was once published can not be removed from the panel.
 */
class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
