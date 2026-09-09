<?php

namespace App\Filament\Resources\PreviewSceneResource\Pages;

use App\Filament\Resources\PreviewSceneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreviewScenes extends ListRecords
{
    protected static string $resource = PreviewSceneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
