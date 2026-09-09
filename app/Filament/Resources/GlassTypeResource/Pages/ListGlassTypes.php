<?php

namespace App\Filament\Resources\GlassTypeResource\Pages;

use App\Filament\Resources\GlassTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGlassTypes extends ListRecords
{
    protected static string $resource = GlassTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
