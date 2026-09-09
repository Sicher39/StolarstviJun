<?php

namespace App\Filament\Resources\DecorResource\Pages;

use App\Filament\Resources\DecorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDecors extends ListRecords
{
    protected static string $resource = DecorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
