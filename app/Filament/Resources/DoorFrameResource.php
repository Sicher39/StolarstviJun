<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoorFrameResource\Pages\CreateDoorFrame;
use App\Filament\Resources\DoorFrameResource\Pages\EditDoorFrame;
use App\Filament\Resources\DoorFrameResource\Pages\ListDoorFrames;
use App\Models\DoorFrame;
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
 * The frame types of the „Ostatní“ section of the configurator: bez zárubně, bezfalcová,
 * obložková and obrácená.
 *
 * The frame is offered per door model, so the models it applies to are chosen here, on the frame,
 * with one multiselect. Only the net price is editable, the gross price is calculated from the
 * central VAT rate and is never stored.
 *
 * The picture is uploaded the same way as the picture of the hardware: the file is staged in the
 * public disk and moved into the single media collection of the row when the record is saved. Its
 * own switch decides whether the configurator shows it, so a picture can be hidden without losing
 * the file or the frame.
 */
class DoorFrameResource extends Resource
{
    protected static ?string $model = DoorFrame::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog dveří';

    protected static ?string $navigationLabel = 'Zárubně';

    protected static ?int $navigationSort = 50;

    public static function getModelLabel(): string
    {
        return 'Zárubeň';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Zárubně';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Název')
                    ->helperText('Například Bez zárubně, Bezfalcová, Obložková nebo Obrácená.')
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
                    ->label('Obrázek zárubně')
                    ->helperText('Nepovinný obrázek, který se zobrazí na kartě zárubně v konfigurátoru.')
                    ->image()
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->directory('door-frame-upload-staging')
                    ->visibility('public')
                    ->dehydrated(),
                Toggle::make('show_image')
                    ->label('Zobrazit obrázek v konfigurátoru')
                    ->helperText('Vypnutím obrázek z veřejného konfigurátoru zmizí. Zárubeň zůstane v nabídce i s cenou, skryje se jen obrázek.')
                    ->default(true),
                Section::make('Kompatibilita s modely dveří')
                    ->description('Zárubeň se v konfigurátoru nabídne jen u modelů, které jsou zde vybrané. Nezaškrtnutý model zárubeň vůbec neuvidí.')
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
                    ->state(fn (DoorFrame $record): string => $record->getFirstMediaUrl('image'))
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
                    ->state(fn (DoorFrame $record): float => VatCalculator::applyRate((float) $record->price_without_vat))
                    ->money('CZK', divideBy: 1)
                    ->alignEnd(),
                TextColumn::make('sort_order')
                    ->label('Pořadí')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktivní')
                    ->boolean(),
                IconColumn::make('show_image')
                    ->label('Obrázek v konfigurátoru')
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
            'index' => ListDoorFrames::route('/'),
            'create' => CreateDoorFrame::route('/create'),
            'edit' => EditDoorFrame::route('/{record}/edit'),
        ];
    }
}
