<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlassTypeResource\Pages\CreateGlassType;
use App\Filament\Resources\GlassTypeResource\Pages\EditGlassType;
use App\Filament\Resources\GlassTypeResource\Pages\ListGlassTypes;
use App\Models\GlassType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GlassTypeResource extends Resource
{
    protected static ?string $model = GlassType::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog dveří';

    protected static ?string $navigationLabel = 'Skla';

    public static function getModelLabel(): string
    {
        return 'Sklo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Skla';
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
                TextInput::make('opacity')
                    ->label('Průhlednost (%)')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->default(100)
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('price_modifier')
                    ->label('Příplatek bez DPH')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01'),
                Toggle::make('active')
                    ->label('Aktivní')
                    ->default(true),
                FileUpload::make('texture_image_upload')
                    ->label('Textura skla')
                    ->image()
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->directory('glass-upload-staging')
                    ->visibility('public')
                    ->dehydrated(),
                FileUpload::make('preview_image_upload')
                    ->label('Náhled skla')
                    ->image()
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->directory('glass-upload-staging')
                    ->visibility('public')
                    ->dehydrated(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('texture_image')
                    ->label('Textura')
                    ->state(fn (GlassType $record): string => $record->getFirstMediaUrl('texture_image'))
                    ->circular(),
                ImageColumn::make('preview_image')
                    ->label('Náhled')
                    ->state(fn (GlassType $record): string => $record->getFirstMediaUrl('preview_image'))
                    ->square(),
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kód')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('opacity')
                    ->label('Průhlednost')
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('price_modifier')
                    ->label('Příplatek bez DPH')
                    ->money('CZK', divideBy: 1)
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktivní')
                    ->boolean(),
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
            'index' => ListGlassTypes::route('/'),
            'create' => CreateGlassType::route('/create'),
            'edit' => EditGlassType::route('/{record}/edit'),
        ];
    }
}
