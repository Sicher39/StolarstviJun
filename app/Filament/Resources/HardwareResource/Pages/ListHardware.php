<?php

namespace App\Filament\Resources\HardwareResource\Pages;

use App\Filament\Resources\HardwareResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHardware extends ListRecords
{
    protected static string $resource = HardwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
