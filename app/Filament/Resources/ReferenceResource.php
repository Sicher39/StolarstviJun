<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferenceResource\Pages\CreateReference;
use App\Filament\Resources\ReferenceResource\Pages\EditReference;
use App\Filament\Resources\ReferenceResource\Pages\ListReferences;
use App\Models\Reference;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ReferenceResource extends Resource
{
    protected static ?string $model = Reference::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?string $navigationLabel = 'Reference';

    public static function getModelLabel(): string
    {
        return 'Reference';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Reference';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('heading')
                    ->label('Nadpis karty')
                    ->helperText('Zobrazuje se na kartě v galerii a v detailu fotografií.')
                    ->required()
                    ->maxLength(120)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Krátký popis (obvykle místo)')
                    ->helperText('Nepovinné. Na kartě se zobrazuje pod nadpisem, obvykle jako místo realizace.')
                    ->maxLength(180)
                    ->rows(2)
                    ->columnSpanFull(),
                Select::make('category')
                    ->label('Kategorie')
                    ->options(Reference::categories())
                    ->required(),
                Select::make('status')
                    ->label('Stav')
                    ->options(Reference::statuses())
                    ->default(Reference::STATUS_DRAFT)
                    ->required(),
                Select::make('placement')
                    ->label('Umístění ve spodní galerii')
                    ->options(Reference::placements())
                    ->placeholder('Nikde – jen na stránce Reference')
                    ->helperText('Umístění je nezávislé na kategorii. Nechte prázdné, pokud se reference nemá objevit ve spodní galerii žádné stránky.')
                    ->nullable()
                    ->columnSpanFull(),
                Section::make('Fotografie')
                    ->description('Fotografie se před nahráním v prohlížeči zmenší na dlouhou hranu 2560 px a převedou na WebP. Pořadí lze měnit přetažením.')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Fotografie')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imagePreviewHeight('140')
                            ->disk(Reference::IMAGES_DISK)
                            ->directory(Reference::IMAGES_DIRECTORY)
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->maxFiles(24)
                            ->extraFieldWrapperAttributes(['data-reference-webp-images' => 'true'])
                            ->helperText('JPG, PNG nebo WebP do 10 MB na soubor. Nahrává se vždy už převedený WebP, originál zůstává v prohlížeči.')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $files = is_array($value) ? $value : [$value];

                                    foreach ($files as $file) {
                                        if (! $file instanceof TemporaryUploadedFile) {
                                            continue;
                                        }

                                        if ($file->getMimeType() === 'image/webp') {
                                            continue;
                                        }

                                        $fail('Fotografii „'.$file->getClientOriginalName().'“ se nepodařilo v prohlížeči převést na WebP. Zkontrolujte, že používáte aktuální prohlížeč s podporou WebP, a vyberte soubor znovu.');

                                        return;
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Náhled')
                    ->state(fn (Reference $record): ?string => $record->coverImagePath())
                    ->disk(Reference::IMAGES_DISK)
                    ->square(),
                TextColumn::make('heading')
                    ->label('Nadpis')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Popis / místo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category')
                    ->label('Kategorie')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Reference::categories()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Reference::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === Reference::STATUS_PUBLISHED ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('placement')
                    ->label('Spodní galerie')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? 'Nikde'
                        : (Reference::placements()[$state] ?? $state))
                    ->placeholder('Nikde')
                    ->sortable(),
                TextColumn::make('images')
                    ->label('Fotografií')
                    ->state(fn (Reference $record): int => count($record->imagePaths()))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategorie')
                    ->options(Reference::categories()),
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(Reference::statuses()),
                SelectFilter::make('placement')
                    ->label('Spodní galerie')
                    ->options(Reference::placements()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Smazat vybrané reference'),
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
            'index' => ListReferences::route('/'),
            'create' => CreateReference::route('/create'),
            'edit' => EditReference::route('/{record}/edit'),
        ];
    }
}
