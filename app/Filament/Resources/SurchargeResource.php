<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurchargeResource\Pages\CreateSurcharge;
use App\Filament\Resources\SurchargeResource\Pages\EditSurcharge;
use App\Filament\Resources\SurchargeResource\Pages\ListSurcharges;
use App\Models\Surcharge;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SurchargeResource extends Resource
{
    protected static ?string $model = Surcharge::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog dveří';

    protected static ?string $navigationLabel = 'Příplatky';

    public static function getModelLabel(): string
    {
        return 'Příplatek';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Příplatky';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Kód')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01'),
                Toggle::make('active')
                    ->label('Aktivní')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kód')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->money('CZK', divideBy: 1)
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktivní')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurcharges::route('/'),
            'create' => CreateSurcharge::route('/create'),
            'edit' => EditSurcharge::route('/{record}/edit'),
        ];
    }
}
