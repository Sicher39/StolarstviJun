<?php

namespace App\Filament\Resources\DataProcessingPurposeResource\Pages;

use App\Filament\Resources\DataProcessingPurposeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDataProcessingPurpose extends EditRecord
{
    protected static string $resource = DataProcessingPurposeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
