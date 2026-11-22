<?php

namespace App\Filament\Resources\DoorFrameResource\Pages;

use App\Filament\Resources\DoorFrameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDoorFrames extends ListRecords
{
    protected static string $resource = DoorFrameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
