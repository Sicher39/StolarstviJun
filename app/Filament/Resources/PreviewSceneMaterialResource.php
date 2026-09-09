<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PreviewSceneMaterialResource\Pages\CreatePreviewSceneMaterial;
use App\Filament\Resources\PreviewSceneMaterialResource\Pages\EditPreviewSceneMaterial;
use App\Filament\Resources\PreviewSceneMaterialResource\Pages\ListPreviewSceneMaterials;
use App\Models\PreviewSceneMaterial;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PreviewSceneMaterialResource extends Resource
{
    protected static ?string $model = PreviewSceneMaterial::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|\UnitEnum|null $navigationGroup = 'Konfigurátor';

    protected static ?string $navigationLabel = 'Materiály prostředí';

    public static function getModelLabel(): string
    {
        return 'Materiál prostředí';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Materiály prostředí';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('preview_scene_id')
                ->label('Prostředí náhledu')
                ->relationship('previewScene', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('type')
                ->label('Typ')
                ->options([
                    'wall' => 'Stěna',
                    'floor' => 'Podlaha',
                ])
                ->required(),
            TextInput::make('name')->label('Název')->required()->maxLength(255),
            TextInput::make('code')->label('Kód')->required()->maxLength(255),
            ColorPicker::make('color')->label('Základní barva')->visible(fn (string $operation, ?PreviewSceneMaterial $record): bool => $record?->type === 'wall' || $operation === 'create'),
            TextInput::make('sort_order')->label('Pořadí')->numeric()->integer()->required()->default(0),
            Toggle::make('active')->label('Aktivní')->default(true),
            self::imageUpload('texture_image_upload', 'Textura materiálu — PNG', true),
            self::imageUpload('preview_image_upload', 'Náhled materiálu — PNG', false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview_image')->label('Náhled')->state(fn (PreviewSceneMaterial $record): string => $record->getFirstMediaUrl('preview_image'))->square(),
                TextColumn::make('previewScene.name')->label('Prostředí')->searchable()->sortable(),
                TextColumn::make('type')->label('Typ')->formatStateUsing(fn (string $state): string => $state === 'wall' ? 'Stěna' : 'Podlaha')->sortable(),
                TextColumn::make('name')->label('Název')->searchable()->sortable(),
                TextColumn::make('sort_order')->label('Pořadí')->sortable(),
                IconColumn::make('active')->label('Aktivní')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreviewSceneMaterials::route('/'),
            'create' => CreatePreviewSceneMaterial::route('/create'),
            'edit' => EditPreviewSceneMaterial::route('/{record}/edit'),
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
            ->directory('preview-scene-material-upload-staging')
            ->visibility('public')
            ->previewable()
            ->imagePreviewHeight('200')
            ->panelLayout('integrated')
            ->required(fn (string $operation): bool => $required && $operation === 'create')
            ->dehydrated();
    }
}
