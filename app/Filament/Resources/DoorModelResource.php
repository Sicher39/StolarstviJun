<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoorModelResource\Pages\CreateDoorModel;
use App\Filament\Resources\DoorModelResource\Pages\EditDoorModel;
use App\Filament\Resources\DoorModelResource\Pages\ListDoorModels;
use App\Models\DoorModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DoorModelResource extends Resource
{
    protected static ?string $model = DoorModel::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog dveří';

    protected static ?string $navigationLabel = 'Modely dveří';

    public static function getModelLabel(): string
    {
        return 'Model dveří';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Modely dveří';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('category')
                    ->label('Kategorie')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Popis')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('base_price_without_vat')
                    ->label('Základní cena bez DPH')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state): mixed => $set('base_price_with_vat', round((float) $state * 1.21, 2))),
                TextInput::make('base_price_with_vat')
                    ->label('Základní cena s DPH (21 %)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                Toggle::make('active')
                    ->label('Aktivní')
                    ->default(true),
                Select::make('decors')
                    ->label('Kompatibilní dekory')
                    ->relationship('decors', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Select::make('glasses')
                    ->label('Kompatibilní skla')
                    ->relationship('glasses', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Select::make('surcharges')
                    ->label('Příplatky')
                    ->relationship('surcharges', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
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
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategorie')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_price_without_vat')
                    ->label('Cena bez DPH')
                    ->money('CZK', divideBy: 1)
                    ->sortable(),
                TextColumn::make('base_price_with_vat')
                    ->label('Cena s DPH (21 %)')
                    ->state(fn (DoorModel $record): float => round((float) $record->base_price_without_vat * 1.21, 2))
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Smazat vybrané modely'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoorModels::route('/'),
            'create' => CreateDoorModel::route('/create'),
            'edit' => EditDoorModel::route('/{record}/edit'),
        ];
    }
}
