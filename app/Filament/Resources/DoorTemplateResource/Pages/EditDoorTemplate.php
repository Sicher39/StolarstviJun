<?php

namespace App\Filament\Resources\DoorTemplateResource\Pages;

use App\Filament\Resources\DoorTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDoorTemplate extends EditRecord
{
    protected static string $resource = DoorTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
