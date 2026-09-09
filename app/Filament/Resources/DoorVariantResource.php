<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoorVariantResource\Pages\CreateDoorVariant;
use App\Filament\Resources\DoorVariantResource\Pages\EditDoorVariant;
use App\Filament\Resources\DoorVariantResource\Pages\ListDoorVariants;
use App\Models\DoorVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DoorVariantResource extends Resource
{
    protected static ?string $model = DoorVariant::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog dveří';

    protected static ?string $navigationLabel = 'Varianty dveří';

    public static function getModelLabel(): string
    {
        return 'Varianta dveří';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Varianty dveří';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Varianta')
                    ->schema([
                        Select::make('door_model_id')
                            ->label('Model dveří')
                            ->relationship('doorModel', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('code')
                            ->label('Název / kód varianty')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('width')
                            ->label('Šířka (mm)')
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('height')
                            ->label('Výška (mm)')
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('opening_direction')
                            ->label('Směr otevírání')
                            ->maxLength(255),
                        TextInput::make('opening_type')
                            ->label('Typ otevírání')
                            ->maxLength(255),
                        Toggle::make('has_glass')
                            ->label('Obsahuje sklo')
                            ->default(false),
                        Toggle::make('sliding_possible')
                            ->label('Posuvná varianta možná')
                            ->default(false),
                        TextInput::make('price_modifier')
                            ->label('Příplatek bez DPH')
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->default(0)
                            ->step('0.01'),
                    ])
                    ->columns(2),
                Section::make('Renderovací vrstvy')
                    ->description('Sklo se vykresluje celoplošně pod dveřmi. Všechny vrstvy musí mít shodný master canvas 426 × 900 px.')
                    ->schema([
                        self::imageUpload('frame_base_upload', 'Podklad zárubně — pouze PNG (frame-base.png)', true),
                        self::maskUpload('frame_mask_upload', 'Maska zárubně — pouze SVG (frame-mask.svg)', true),
                        self::maskUpload('door_mask_upload', 'Maska křídla s otvory pro sklo — pouze SVG (door-mask.svg)', true),
                        self::imageUpload('construction_overlay_upload', 'Konstrukční spáry a stíny — pouze PNG (construction-overlay.png)', true),
                        self::imageUpload('handle_overlay_upload', 'Klika a kování — pouze PNG (handle-overlay.png)', true),
                        self::imageUpload('source_reference_upload', 'Referenční obrázek pro kontrolu — pouze PNG (source-reference.png)', false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('doorModel.name')
                    ->label('Model dveří')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kód')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('width')
                    ->label('Šířka')
                    ->sortable(),
                TextColumn::make('height')
                    ->label('Výška')
                    ->sortable(),
                IconColumn::make('has_glass')
                    ->label('Sklo')
                    ->boolean(),
                TextColumn::make('price_modifier')
                    ->label('Příplatek bez DPH')
                    ->money('CZK', divideBy: 1)
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Smazat vybrané varianty'),
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
            'index' => ListDoorVariants::route('/'),
            'create' => CreateDoorVariant::route('/create'),
            'edit' => EditDoorVariant::route('/{record}/edit'),
        ];
    }

    private static function imageUpload(string $name, string $label, bool $required): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->acceptedFileTypes(['image/png'])
            ->maxSize(10240)
            ->disk('public')
            ->directory('door-variant-upload-staging')
            ->visibility('public')
            ->previewable()
            ->imagePreviewHeight('240')
            ->panelLayout('integrated')
            ->required(fn (string $operation): bool => $required && $operation === 'create')
            ->dehydrated();
    }

    private static function maskUpload(string $name, string $label, bool $required): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->acceptedFileTypes(['image/svg+xml'])
            ->maxSize(10240)
            ->disk('public')
            ->directory('door-variant-upload-staging')
            ->visibility('public')
            ->previewable()
            ->imagePreviewHeight('240')
            ->panelLayout('integrated')
            ->required(fn (string $operation): bool => $required && $operation === 'create')
            ->dehydrated();
    }
}
