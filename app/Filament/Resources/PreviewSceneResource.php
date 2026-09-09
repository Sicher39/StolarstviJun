<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PreviewSceneResource\Pages\CreatePreviewScene;
use App\Filament\Resources\PreviewSceneResource\Pages\EditPreviewScene;
use App\Filament\Resources\PreviewSceneResource\Pages\ListPreviewScenes;
use App\Models\PreviewScene;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PreviewSceneResource extends Resource
{
    protected static ?string $model = PreviewScene::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'Konfigurátor';

    protected static ?string $navigationLabel = 'Prostředí náhledu';

    public static function getModelLabel(): string
    {
        return 'Prostředí náhledu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Prostředí náhledu';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Prostředí')
                ->schema([
                    TextInput::make('name')->label('Název')->required()->maxLength(255),
                    TextInput::make('code')->label('Kód')->required()->maxLength(255)->unique(ignoreRecord: true),
                    Toggle::make('active')->label('Aktivní')->default(true),
                ]),
            Section::make('Společné vrstvy místnosti')
                ->description('Geometrie je pevná: scéna 1400 × 1100 px, dveřní canvas 426 × 900 px na pozici X 487 / Y 95. Pokoj za průchodem se zobrazí i před výběrem dveří.')
                ->schema([
                    self::imageUpload('scene_reference_upload', 'Kontrolní náhled bez dveří — PNG (scene-reference.png)', false),
                    self::imageUpload('scene_base_upload', 'Světlo a stíny scény — PNG (scene-lighting-overlay.png)', true),
                    self::imageUpload('scene_foreground_upload', 'Prvky pokoje — PNG (scene-foreground-overlay.png)', true),
                    self::imageUpload('doorway_depth_upload', 'Hloubka stavebního průchodu — PNG (doorway-depth-overlay.png)', true),
                    self::maskUpload('wall_mask_upload', 'Maska stěny — SVG (wall-mask.svg)', true),
                    self::maskUpload('floor_mask_upload', 'Maska podlahy — SVG (floor-mask.svg)', true),
                    self::maskUpload('doorway_mask_upload', 'Maska stavebního průchodu — SVG (doorway-mask.svg)', true),
                    self::imageUpload('interior_background_upload', 'Pokoj za dveřmi — PNG (room-behind-door.png)', true),
                    self::imageUpload('interior_background_blurred_upload', 'Rozostřený pokoj za dveřmi — PNG (room-behind-door-blurred.png)', true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Název')->searchable()->sortable(),
                TextColumn::make('code')->label('Kód')->searchable()->sortable(),
                IconColumn::make('active')->label('Aktivní')->boolean(),
                TextColumn::make('updated_at')->label('Upraveno')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreviewScenes::route('/'),
            'create' => CreatePreviewScene::route('/create'),
            'edit' => EditPreviewScene::route('/{record}/edit'),
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
            ->directory('preview-scene-upload-staging')
            ->visibility('public')
            ->previewable()
            ->imagePreviewHeight('200')
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
            ->directory('preview-scene-upload-staging')
            ->visibility('public')
            ->previewable()
            ->imagePreviewHeight('200')
            ->panelLayout('integrated')
            ->required(fn (string $operation): bool => $required && $operation === 'create')
            ->dehydrated();
    }
}
