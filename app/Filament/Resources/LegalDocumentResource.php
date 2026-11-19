<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegalDocumentResource\Pages\CreateLegalDocument;
use App\Filament\Resources\LegalDocumentResource\Pages\EditLegalDocument;
use App\Filament\Resources\LegalDocumentResource\Pages\ListLegalDocuments;
use App\Filament\Resources\LegalDocumentResource\RelationManagers\RevisionsRelationManager;
use App\Models\LegalDocument;
use App\Services\Legal\LegalContentRenderer;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Legal documents of the site, today the privacy policy behind /ochrana-osobnich-udalu.
 *
 * The record is edited in place (one per type and locale), never duplicated, and there is no delete
 * action: a legal document that was once published must stay reproducible. Every change of a
 * published record is snapshotted by the model and the snapshots are listed in the "Historie
 * zveřejnění" relation manager.
 *
 * The content is HTML written in a rich editor. `{{ variable }}` is filled from the contact record
 * (see LegalContentRenderer) and `[DOPLNIT: ...]` marks a fact the owner still has to confirm; the
 * table shows the remaining markers, so an unfinished document is visible in the list.
 */
class LegalDocumentResource extends Resource
{
    protected static ?string $model = LegalDocument::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'GDPR';

    protected static ?string $navigationLabel = 'Právní dokumenty';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'Právní dokument';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Právní dokumenty';
    }

    /**
     * The form sections, in the order the editor works through them.
     *
     * @return array<string, list<Component>>
     */
    public static function formSections(): array
    {
        return [
            'Dokument' => [
                Select::make('type')
                    ->label('Typ dokumentu')
                    ->helperText('Typ a jazyk určují, kterou stránku dokument plní. Po uložení už typ měnit nelze.')
                    ->options(LegalDocument::typeOptions())
                    ->required()
                    ->disabled(fn (?LegalDocument $record): bool => $record !== null)
                    ->dehydrated(),
                Select::make('locale')
                    ->label('Jazyk')
                    ->helperText('Web je zatím pouze v češtině.')
                    ->options(LegalDocument::localeOptions())
                    ->required()
                    ->default(LegalDocument::DEFAULT_LOCALE),
                TextInput::make('title')
                    ->label('Název dokumentu')
                    ->helperText('Zobrazuje se jako nadpis na veřejné stránce.')
                    ->required()
                    ->maxLength(255),
                Textarea::make('perex')
                    ->label('Úvodní odstavec')
                    ->helperText('Krátké uvedení pod nadpisem. Nepovinné.')
                    ->rows(3)
                    ->columnSpanFull(),
            ],

            'Obsah' => [
                RichEditor::make('content')
                    ->label('Text dokumentu')
                    ->helperText(self::contentHelpText())
                    ->required()
                    ->columnSpanFull(),
            ],

            'Zveřejnění' => [
                TextInput::make('version')
                    ->label('Verze')
                    ->helperText('Například 1.0 nebo 1.1. Ukládá se i do historie.')
                    ->required()
                    ->maxLength(32)
                    ->default(LegalDocument::DEFAULT_VERSION),
                DatePicker::make('effective_from')
                    ->label('Účinnost od')
                    ->helperText('Datum, od kterého text platí. Když datum neznáte, nechte pole prázdné a doplňte ho v textu.'),
                DateTimePicker::make('published_at')
                    ->label('Zveřejněno')
                    ->helperText('Prázdné pole znamená koncept: na webu se dál zobrazuje poslední zveřejněná verze.')
                    ->seconds(false),
            ],

            'Poznámky' => [
                Textarea::make('notes')
                    ->label('Poznámky')
                    ->helperText('Interní poznámky k dokumentu. Nikam se nepublikují.')
                    ->rows(4)
                    ->columnSpanFull(),
            ],
        ];
    }

    /**
     * The variables and the placeholder marker are explained where the text is written, so the
     * editor does not have to read the source code.
     */
    public static function contentHelpText(): string
    {
        return 'Můžete použít proměnné {{ company_name }}, {{ street }}, {{ city }}, {{ full_address }}, '
            .'{{ registration_number }}, {{ vat_number }}, {{ data_box }}, {{ bank_account }}, '
            .'{{ registry_note }}, {{ contact_person }}, {{ email }}, {{ phone }} a '
            .'{{ inquiry_retention_period }}; doplní se z kontaktních údajů a z nastavení níže. '
            .'Místo, kam patří přehled z administrace, označte značkou: {{ processing_purposes }} vloží '
            .'karty účelů zpracování (do zásad ochrany osobních údajů) a {{ cookie_services }} vloží '
            .'karty cookies a služeb (do zásad používání cookies). Když značku v textu nemáte, přehled '
            .'se zobrazí pod dokumentem. '
            .'Místo, které je potřeba doplnit, označte [DOPLNIT: popis] – zůstane na stránce viditelné, '
            .'dokud ho nevyplníte.';
    }

    public static function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (self::formSections() as $heading => $components) {
            $sections[] = Section::make($heading)
                ->schema($components)
                ->columns(2)
                ->columnSpanFull();
        }

        return $schema->components($sections);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('type')
            ->columns([
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LegalDocument::typeOptions()[$state] ?? $state)
                    ->searchable(),
                TextColumn::make('locale')
                    ->label('Jazyk')
                    ->badge(),
                TextColumn::make('title')
                    ->label('Název')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('version')
                    ->label('Verze')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Zveřejněno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('koncept')
                    ->sortable(),
                TextColumn::make('effective_from')
                    ->label('Účinnost od')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('placeholders')
                    ->label('K doplnění')
                    ->state(fn (LegalDocument $record): string => implode('; ', app(LegalContentRenderer::class)->placeholders($record->content)) ?: '—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            // No delete or bulk delete action: a published legal text has to stay reproducible.
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalDocuments::route('/'),
            'create' => CreateLegalDocument::route('/create'),
            'edit' => EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
