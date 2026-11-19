<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoorModelResource\Pages\CreateDoorModel;
use App\Filament\Resources\DoorModelResource\Pages\EditDoorModel;
use App\Filament\Resources\DoorModelResource\Pages\ListDoorModels;
use App\Models\Decor;
use App\Models\DoorModel;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                Section::make('Výchozí volby konfigurátoru')
                    ->description(fn (?DoorModel $record): string => $record === null
                        ? 'Nepovinné a vyplnitelné až po uložení modelu. Model teď uložte, poté přidejte varianty a výchozí variantu i dekor vyberte při úpravě modelu.'
                        : 'Nepovinné. Nevyplněnou volbu nahradí konfigurátor první vloženou variantou a prvním dostupným dekorem modelu.')
                    ->schema([
                        // The defaults can only point at variants/decors that exist, so they must never be part of
                        // the create payload: a hidden field is not dehydrated by Filament and the model can be
                        // saved before any variant exists.
                        Select::make('default_variant_id')
                            ->label('Výchozí varianta')
                            ->helperText('Nabízejí se pouze varianty tohoto modelu. Varianty přidejte v sekci „Varianty dveří“ a výchozí volbu pak nastavte při úpravě modelu.')
                            ->hiddenOn('create')
                            ->options(fn (?DoorModel $record): array => $record === null
                                ? []
                                : $record->variants()->orderBy('id')->pluck('code', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->rules([
                                fn (?DoorModel $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    if ($record === null || ! $record->variants()->whereKey($value)->exists()) {
                                        $fail('Vybraná výchozí varianta nepatří tomuto modelu.');
                                    }
                                },
                            ]),
                        Select::make('default_decor_id')
                            ->label('Výchozí dekor')
                            ->helperText('Nabízejí se pouze aktivní dekory přiřazené tomuto modelu. Výchozí dekor nastavte při úpravě modelu.')
                            ->hiddenOn('create')
                            ->options(fn (?DoorModel $record): array => $record === null
                                ? []
                                : $record->decors()->where('active', true)->orderBy('name')->get()->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->rules([
                                fn (Get $get, ?DoorModel $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    if (! Decor::query()->whereKey($value)->where('active', true)->exists()) {
                                        $fail('Vybraný výchozí dekor není aktivní.');

                                        return;
                                    }

                                    $pendingDecorIds = collect($get('decors') ?? [])
                                        ->map(fn (mixed $decorId): int => (int) $decorId)
                                        ->all();

                                    $isAttachedToModel = in_array((int) $value, $pendingDecorIds, true)
                                        || ($record !== null && $record->decors()->whereKey($value)->exists());

                                    if (! $isAttachedToModel) {
                                        $fail('Vybraný výchozí dekor není kompatibilní s tímto modelem.');
                                    }
                                },
                            ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
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
