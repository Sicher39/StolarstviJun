<?php

namespace App\Filament\Resources;

use App\Enums\Compliance\ScriptPosition;
use App\Enums\Compliance\TrackingCategory;
use App\Filament\Resources\TrackingScriptResource\Pages\CreateTrackingScript;
use App\Filament\Resources\TrackingScriptResource\Pages\EditTrackingScript;
use App\Filament\Resources\TrackingScriptResource\Pages\ListTrackingScripts;
use App\Models\TrackingScript;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The cookies and tracking services the site uses.
 *
 * Two kinds of record share the table, because the cookie policy publishes them in one overview:
 *
 * - a `Nezbytné` record documents a first-party technical cookie (session, form protection, the stored
 *   cookie choice). Such a record carries no code: it explains a cookie the application sets itself.
 * - a record of an analytical, marketing or preference category carries the snippet of the provider in
 *   `code`. The browser executes it only after the visitor agreed with that category, and the consent
 *   requirement of such a category can not be switched off.
 *
 * The application ships no vendor snippet: the operator pastes the code the provider gave them, so
 * nothing is claimed about a service that is not configured.
 */
class TrackingScriptResource extends Resource
{
    protected static ?string $model = TrackingScript::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|\UnitEnum|null $navigationGroup = 'GDPR';

    protected static ?string $navigationLabel = 'Tracking scripty';

    protected static ?int $navigationSort = 50;

    public static function getModelLabel(): string
    {
        return 'Tracking script';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tracking scripty';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Volitelné scripty se spouštějí až po souhlasu')
                ->description('Záznam v analytické, marketingové nebo preferenční kategorii vyžaduje souhlas vždy, i když přepínač níže vypnete. Do pole „Kód" vložte přesně to, co vám poskytovatel nástroje vydal; aplikace žádný kód sama nedoplňuje.')
                ->warning()
                ->columnSpanFull(),
            Section::make('Zařazení')
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->helperText('U nezbytných cookies napište, co cookie dělá, například „Relace prohlížení".')
                        ->required()
                        ->maxLength(255),
                    Select::make('category')
                        ->label('Kategorie')
                        ->options(TrackingCategory::options())
                        ->required()
                        ->live(),
                    Select::make('position')
                        ->label('Umístění kódu')
                        ->options(ScriptPosition::options())
                        ->required()
                        ->default(ScriptPosition::BodyEnd->value),
                    TextInput::make('priority')
                        ->label('Pořadí')
                        ->helperText('Nižší číslo se vkládá dřív.')
                        ->numeric()
                        ->default(100)
                        ->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Popis pro zásady cookies')
                ->schema([
                    Textarea::make('description')
                        ->label('K čemu cookie nebo nástroj slouží')
                        ->rows(3)
                        ->columnSpanFull(),
                    TextInput::make('provider_name')
                        ->label('Poskytovatel')
                        ->helperText('U vlastních technických cookies nechte prázdné, doplní se název firmy.')
                        ->maxLength(255),
                    TextInput::make('provider_privacy_url')
                        ->label('Zásady ochrany údajů poskytovatele')
                        ->url()
                        ->maxLength(255),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Spuštění')
                ->schema([
                    Textarea::make('code')
                        ->label('Kód')
                        ->helperText('Nepovinné. Bez kódu se nic nespouští – záznam pak slouží jen jako vysvětlení cookie v zásadách.')
                        ->rows(6)
                        ->columnSpanFull(),
                    Toggle::make('enabled')
                        ->label('Zapnuto')
                        ->helperText('Vypnutý záznam se nikde nespouští a v zásadách cookies se nezobrazuje.')
                        ->default(false),
                    Toggle::make('requires_consent')
                        ->label('Vyžaduje souhlas')
                        ->helperText('U volitelných kategorií se přepínač uloží jako zapnutý, protože bez souhlasu se script nespouští.')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategorie')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof TrackingCategory
                        ? $state->label()
                        : (TrackingCategory::tryFrom((string) $state)?->label() ?? '—')),
                TextColumn::make('position')
                    ->label('Umístění')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ScriptPosition
                        ? $state->label()
                        : (ScriptPosition::tryFrom((string) $state)?->label() ?? '—'))
                    ->toggleable(),
                TextColumn::make('provider_name')
                    ->label('Poskytovatel')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('enabled')
                    ->label('Zapnuto')
                    ->boolean(),
                IconColumn::make('requires_consent')
                    ->label('Souhlas')
                    ->boolean(),
                TextColumn::make('priority')
                    ->label('Pořadí')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrackingScripts::route('/'),
            'create' => CreateTrackingScript::route('/create'),
            'edit' => EditTrackingScript::route('/{record}/edit'),
        ];
    }
}
