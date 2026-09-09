<?php

namespace App\Filament\Resources\SurchargeResource\Pages;

use App\Filament\Resources\SurchargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSurcharges extends ListRecords
{
    protected static string $resource = SurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
