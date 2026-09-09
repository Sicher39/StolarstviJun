<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InquiryResource\Pages\EditInquiry;
use App\Filament\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\Inquiry;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Poptávky';

    protected static ?string $navigationLabel = 'Poptávky';

    public static function getModelLabel(): string
    {
        return 'Poptávka';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Poptávky';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('door_model_id')
                    ->label('Model dveří')
                    ->relationship('doorModel', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('door_variant_id')
                    ->label('Varianta dveří')
                    ->relationship('doorVariant', 'code')
                    ->searchable()
                    ->preload(),
                Select::make('decor_id')
                    ->label('Dekor')
                    ->relationship('decor', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('glass_type_id')
                    ->label('Sklo')
                    ->relationship('glassType', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('surcharges')
                    ->label('Příplatky')
                    ->relationship('surcharges', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('U pivot ceny příplatků se zde spravuje pouze přiřazení.'),
                TextInput::make('customer_name')
                    ->label('Jméno zákazníka')
                    ->required()
                    ->maxLength(255),
                TextInput::make('customer_email')
                    ->label('E-mail zákazníka')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('customer_phone')
                    ->label('Telefon')
                    ->maxLength(255),
                Textarea::make('customer_message')
                    ->label('Zpráva zákazníka')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01'),
                TextInput::make('price_with_vat')
                    ->label('Cena s DPH')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01'),
                Textarea::make('configuration')
                    ->label('Snapshot konfigurace (JSON)')
                    ->formatStateUsing(fn ($state): string => $state === null ? '' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->dehydrateStateUsing(fn (?string $state): ?array => blank($state) ? null : json_decode($state, true))
                    ->rows(10)
                    ->columnSpanFull(),
                Textarea::make('crm_payload')
                    ->label('CRM payload (JSON)')
                    ->formatStateUsing(fn ($state): string => $state === null ? '' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->dehydrateStateUsing(fn (?string $state): ?array => blank($state) ? null : json_decode($state, true))
                    ->rows(10)
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->label('Stav')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Zákazník')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doorModel.name')
                    ->label('Model dveří')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doorVariant.code')
                    ->label('Varianta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_with_vat')
                    ->label('Cena s DPH')
                    ->money('CZK', divideBy: 1)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInquiries::route('/'),
            'edit' => EditInquiry::route('/{record}/edit'),
        ];
    }
}
