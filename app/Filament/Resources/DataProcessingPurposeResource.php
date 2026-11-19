<?php

namespace App\Filament\Resources;

use App\Enums\Compliance\LegalBasis;
use App\Filament\Resources\DataProcessingPurposeResource\Pages\CreateDataProcessingPurpose;
use App\Filament\Resources\DataProcessingPurposeResource\Pages\EditDataProcessingPurpose;
use App\Filament\Resources\DataProcessingPurposeResource\Pages\ListDataProcessingPurposes;
use App\Models\DataProcessingPurpose;
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
 * The purposes the workshop processes personal data for.
 *
 * The record is the description of the scope of processing: the privacy policy publishes every active
 * purpose, in the order of `priority`, at the `{{ processing_purposes }}` token of its text. Nothing of
 * this is retyped into the legal document, so a change of processing is made exactly once - here.
 *
 * `context` is the stable key of a seeded purpose (Database\Seeders\DataProcessingPurposeSeeder).
 * It is what the consent record of an inquiry points at, so it should not be renamed for the purposes
 * the seeder created.
 */
class DataProcessingPurposeResource extends Resource
{
    protected static ?string $model = DataProcessingPurpose::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'GDPR';

    protected static ?string $navigationLabel = 'Účely zpracování';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return 'Účel zpracování';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Účely zpracování';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Změna se projeví na zásadách ochrany osobních údajů')
                ->description('Publikované zásady vypisují právě aktivní účely, takže uložená změna se na veřejné stránce objeví okamžitě. Deaktivovaný účel se přestane zveřejňovat, ale zůstane v administraci i v záznamech souhlasů.')
                ->info()
                ->columnSpanFull(),
            Section::make('Účel')
                ->schema([
                    TextInput::make('name')
                        ->label('Název účelu')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('context')
                        ->label('Interní klíč')
                        ->helperText('Stálý klíč, kterým se účel propojuje se záznamy souhlasů. U účelu „Vyřízení poptávky" ho neměňte, používá ho poptávkový formulář.')
                        ->maxLength(255),
                    Select::make('legal_basis')
                        ->label('Právní základ')
                        ->options(LegalBasis::options())
                        ->required(),
                    TextInput::make('retention_period')
                        ->label('Doba uchovávání')
                        ->helperText('Uveďte ji i s odkazem na předpis, ze kterého vyplývá.')
                        ->maxLength(255),
                    TextInput::make('priority')
                        ->label('Pořadí')
                        ->helperText('Nižší číslo se zobrazuje dřív.')
                        ->numeric()
                        ->default(100)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Aktivní (zveřejňovat)')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Popis pro veřejnou stránku')
                ->schema([
                    Textarea::make('description')
                        ->label('Popis účelu')
                        ->rows(4)
                        ->columnSpanFull(),
                    Textarea::make('personal_data_categories')
                        ->label('Jaké osobní údaje se zpracovávají')
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('recipients')
                        ->label('Příjemci')
                        ->helperText('Jmenujte jen dodavatele, kteří pro firmu skutečně pracují.')
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('third_country_transfer')
                        ->label('Předání do třetích zemí')
                        ->helperText('Vyplňte jen tehdy, pokud k předání opravdu dochází. Prázdné pole se nezveřejňuje.')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(1)
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
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('context')
                    ->label('Klíč')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('legal_basis')
                    ->label('Právní základ')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof LegalBasis
                        ? $state->label()
                        : (LegalBasis::tryFrom((string) $state)?->label() ?? '—')),
                IconColumn::make('is_active')
                    ->label('Aktivní')
                    ->boolean(),
                TextColumn::make('priority')
                    ->label('Pořadí')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    /**
     * The select options offered wherever a purpose can be referenced.
     *
     * @return array<int, string>
     */
    public static function activeOptions(): array
    {
        return DataProcessingPurpose::query()
            ->active()
            ->ordered()
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataProcessingPurposes::route('/'),
            'create' => CreateDataProcessingPurpose::route('/create'),
            'edit' => EditDataProcessingPurpose::route('/{record}/edit'),
        ];
    }
}
