<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HardwareResource\Pages\CreateHardware;
use App\Filament\Resources\HardwareResource\Pages\EditHardware;
use App\Filament\Resources\HardwareResource\Pages\ListHardware;
use App\Models\Hardware;
use App\Services\VatCalculator;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The hardware of the „Ostatní“ section of the configurator: handles and fittings the visitor can
 * add to a door.
 *
 * The image is uploaded the same way as the images of the glass types: the file is staged in the
 * public disk and moved into the single media collection of the row when the record is saved. Only
 * the net price is editable, the gross price is calculated from the central VAT rate and is never
 * stored. The models the hardware fits are chosen here, on the hardware, with one multiselect.
 */
class HardwareResource extends Resource
{
    protected static ?string $model = Hardware::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog dveří';

    protected static ?string $navigationLabel = 'Kování';

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return 'Kování';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Kování';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state): mixed => $set('price_with_vat', VatCalculator::applyRate((float) $state))),
                TextInput::make('price_with_vat')
                    ->label(fn (): string => 'Cena s DPH ('.VatCalculator::formattedRate().')')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sort_order')
                    ->label('Pořadí')
                    ->helperText('Menší číslo se v konfigurátoru nabídne dříve.')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('active')
                    ->label('Aktivní')
                    ->default(true),
                FileUpload::make('image_upload')
                    ->label('Obrázek kování')
                    ->image()
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->directory('hardware-upload-staging')
                    ->visibility('public')
                    ->dehydrated(),
                Section::make('Kompatibilita s modely dveří')
                    ->description('Kování se v konfigurátoru nabídne jen u modelů, které jsou zde vybrané. Nezaškrtnutý model kování vůbec neuvidí.')
                    ->schema([
                        Select::make('doorModels')
                            ->label('Modely dveří')
                            ->relationship('doorModels', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Obrázek')
                    ->state(fn (Hardware $record): string => $record->getFirstMediaUrl('image'))
                    ->square(),
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->money('CZK', divideBy: 1)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('price_with_vat')
                    ->label(fn (): string => 'Cena s DPH ('.VatCalculator::formattedRate().')')
                    ->state(fn (Hardware $record): float => VatCalculator::applyRate((float) $record->price_without_vat))
                    ->money('CZK', divideBy: 1)
                    ->alignEnd(),
                TextColumn::make('sort_order')
                    ->label('Pořadí')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktivní')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('name'))
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
            'index' => ListHardware::route('/'),
            'create' => CreateHardware::route('/create'),
            'edit' => EditHardware::route('/{record}/edit'),
        ];
    }
}
