<?php

namespace App\Filament\Resources\DataProcessingPurposeResource\Pages;

use App\Filament\Resources\DataProcessingPurposeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDataProcessingPurposes extends ListRecords
{
    protected static string $resource = DataProcessingPurposeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
