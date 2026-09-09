<?php

namespace App\Filament\Resources\DoorModelResource\Pages;

use App\Filament\Resources\DoorModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDoorModel extends EditRecord
{
    protected static string $resource = DoorModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
