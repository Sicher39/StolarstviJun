<?php

namespace App\Filament\Resources;

use App\Enums\Compliance\ConsentType;
use App\Filament\Resources\ConsentRecordResource\Pages\ListConsentRecords;
use App\Filament\Resources\ConsentRecordResource\Pages\ViewConsentRecord;
use App\Models\ConsentRecord;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The evidence of the consents the site collected.
 *
 * A record is written by the site, never by the panel: the cookie dialog stores the chosen categories
 * and the inquiry form stores the GDPR acknowledgement of the submission. The resource therefore only
 * lists and shows the rows; the single action it offers is marking a consent as withdrawn, which an
 * operator does after a verified request of the data subject.
 *
 * Nothing here is editable or deletable: evidence that can be rewritten at will is no evidence.
 */
class ConsentRecordResource extends Resource
{
    protected static ?string $model = ConsentRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'GDPR';

    protected static ?string $navigationLabel = 'Záznamy souhlasů';

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return 'Záznam souhlasu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Záznamy souhlasů';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Záznam se needituje')
                ->description('Záznam vznikl na webu v okamžiku, kdy návštěvník odeslal formulář nebo zvolil cookies. Slouží jako důkaz, proto ho nelze přepsat; jediná dostupná změna je označení „odvoláno" po ověřené žádosti subjektu údajů.')
                ->info()
                ->columnSpanFull(),
            Section::make('Souhlas')
                ->schema([
                    TextInput::make('consent_uuid')
                        ->label('Identifikátor')
                        ->disabled(),
                    TextInput::make('type')
                        ->label('Typ')
                        ->disabled()
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof ConsentType
                            ? $state->label()
                            : (ConsentType::tryFrom((string) $state)?->label() ?? '—')),
                    TextInput::make('version')
                        ->label('Verze dokumentu')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('source')
                        ->label('Zdroj')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('channel')
                        ->label('Kanál')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('purpose')
                        ->label('Účel zpracování')
                        ->disabled()
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Subjekt údajů')
                ->schema([
                    TextInput::make('subject_name')
                        ->label('Jméno')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('subject_email')
                        ->label('E-mail')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('subject_phone')
                        ->label('Telefon')
                        ->disabled()
                        ->placeholder('—'),
                ])
                ->columns(3)
                ->columnSpanFull(),
            Section::make('Obsah souhlasu')
                ->schema([
                    KeyValue::make('preferences')
                        ->label('Uložený obsah (snapshot)')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make('Technické údaje a časy')
                ->schema([
                    TextInput::make('ip_hash')
                        ->label('Hash IP adresy')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('user_agent_hash')
                        ->label('Hash prohlížeče')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('accepted_at')
                        ->label('Přijato')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('rejected_at')
                        ->label('Odmítnuto')
                        ->disabled()
                        ->placeholder('—'),
                    TextInput::make('withdrawn_at')
                        ->label('Odvoláno')
                        ->disabled()
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ConsentType
                        ? $state->label()
                        : (ConsentType::tryFrom((string) $state)?->label() ?? '—')),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (ConsentRecord $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'Přijato' => 'success',
                        'Odmítnuto', 'Odvoláno' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('version')
                    ->label('Verze')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Zdroj')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('subject_name')
                    ->label('Subjekt')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('subject_email')
                    ->label('E-mail')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('granted')
                    ->label('Povolené kategorie')
                    ->state(fn (ConsentRecord $record): string => implode(', ', $record->grantedCookieCategories()) ?: '—')
                    ->toggleable(),
                TextColumn::make('accepted_at')
                    ->label('Přijato')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('withdrawn_at')
                    ->label('Odvoláno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(ConsentType::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('withdraw')
                    ->label('Odvolat')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Odvolat souhlas')
                    ->modalDescription('Záznam se označí jako odvolaný. Použijte ji po ověřené žádosti subjektu údajů; záznamy se nemažou.')
                    ->visible(fn (ConsentRecord $record): bool => $record->isAccepted())
                    ->action(fn (ConsentRecord $record): bool => $record->update(['withdrawn_at' => now()])),
            ])
            ->toolbarActions([]);
    }

    /**
     * Evidence is written by the site, never by the panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsentRecords::route('/'),
            'view' => ViewConsentRecord::route('/{record}'),
        ];
    }
}
