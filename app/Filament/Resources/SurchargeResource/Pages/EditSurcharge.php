<?php

namespace App\Filament\Resources\SurchargeResource\Pages;

use App\Filament\Resources\SurchargeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSurcharge extends EditRecord
{
    protected static string $resource = SurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
