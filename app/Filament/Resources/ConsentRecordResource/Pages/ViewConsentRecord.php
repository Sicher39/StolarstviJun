<?php

namespace App\Filament\Resources\ConsentRecordResource\Pages;

use App\Filament\Resources\ConsentRecordResource;
use Filament\Resources\Pages\ViewRecord;

class ViewConsentRecord extends ViewRecord
{
    protected static string $resource = ConsentRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
