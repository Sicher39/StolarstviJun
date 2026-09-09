<?php

namespace App\Filament\Resources\PreviewSceneMaterialResource\Pages;

use App\Filament\Resources\PreviewSceneMaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPreviewSceneMaterials extends ListRecords
{
    protected static string $resource = PreviewSceneMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Vše'),
            'walls' => Tab::make('Stěny')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', 'wall')),
            'floors' => Tab::make('Podlahy')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', 'floor')),
        ];
    }
}
