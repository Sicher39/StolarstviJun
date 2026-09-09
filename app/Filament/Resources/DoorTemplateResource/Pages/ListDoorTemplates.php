<?php

namespace App\Filament\Resources\DoorTemplateResource\Pages;

use App\Filament\Resources\DoorTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDoorTemplates extends ListRecords
{
    protected static string $resource = DoorTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
