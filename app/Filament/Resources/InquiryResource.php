<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InquiryResource\Pages\EditInquiry;
use App\Filament\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\Inquiry;
use App\Services\Inquiry\InquiryPreviewResolver;
use App\Services\Inquiry\InquirySnapshotPresenter;
use App\Services\Inquiry\InquiryStatus;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Inquiries sent from the configurator, edited as one record in two columns: the customer on the
 * left, the chosen configuration and the price on the right, the best available door image below.
 *
 * The `configuration` and `crm_payload` JSON snapshots are never edited here. `configuration` is shown
 * as read only text through InquirySnapshotPresenter, because it is the historical record of what the
 * customer saw and raw JSON in a textarea is unreadable and easy to corrupt. Both columns stay
 * editable, so an inquiry can still be corrected by hand.
 *
 * The status is chosen from the four cases of InquiryStatus and is shown as a Czech badge in the
 * table, so the value stays a controlled vocabulary instead of free text.
 */
class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Poptávky';

    protected static ?string $navigationLabel = 'Poptávky';

    public static function getModelLabel(): string
    {
        return 'Poptávka';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Poptávky';
    }

    public static function form(Schema $schema): Schema
    {
        $fields = self::formFields();

        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema([
                        Group::make([
                            Section::make('Zákazník')
                                ->description('Kontaktní údaje tak, jak je zákazník vyplnil v konfigurátoru. Úprava se uloží pouze do této poptávky.')
                                ->schema($fields['customer'])
                                ->columns(1),
                            Section::make('Stav poptávky')
                                ->description('Interní stav, se kterým pracujete v administraci. Zákazník ho nevidí.')
                                ->schema($fields['status'])
                                ->columns(1),
                        ])->columnSpan(1),
                        Group::make([
                            Section::make('Vybraná konfigurace')
                                ->description('Volby z konfigurátoru. Jejich změna ovlivní jen tuto poptávku, katalog dveří zůstává beze změny.')
                                ->schema($fields['configuration'])
                                ->columns(1),
                            Section::make('Cena')
                                ->description('Cena uložená u poptávky v Kč. Obě hodnoty jdou ručně upravit, kontrolujte je proto společně.')
                                ->schema($fields['price'])
                                ->columns(1),
                        ])->columnSpan(1),
                        self::previewSection($fields['preview']),
                        self::snapshotSection(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The editable fields and the read only blocks of the record, grouped by the section they are
     * rendered in. Extracted from `form()` so the layout can be asserted without booting a panel.
     *
     * @return array<string, list<Component>>
     */
    public static function formFields(): array
    {
        return [
            'customer' => [
                Text::make(fn (?Inquiry $record): string => self::inquiryNumber($record))
                    ->badge()
                    ->color('primary'),
                Text::make(fn (?Inquiry $record): string => self::receivedLine($record))
                    ->color('gray'),
                TextInput::make('customer_name')
                    ->label('Jméno a příjmení')
                    ->required()
                    ->maxLength(255),
                TextInput::make('customer_email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('customer_phone')
                    ->label('Telefon')
                    ->maxLength(255),
                Textarea::make('customer_message')
                    ->label('Zpráva zákazníka')
                    ->rows(6)
                    ->columnSpanFull(),
                Text::make(fn (?Inquiry $record): ?string => self::anonymizedLine($record))
                    ->color('warning')
                    ->visible(fn (?Inquiry $record): bool => $record?->isAnonymized() ?? false),
            ],
            'status' => [
                Select::make('status')
                    ->label('Stav')
                    ->options(InquiryStatus::options())
                    ->default(InquiryStatus::New->value)
                    ->required()
                    ->helperText('Vyberte jeden ze čtyř interních stavů. Volný text se neukládá, aby poptávky zůstaly roztříděné podle stejných pravidel.'),
            ],
            'configuration' => [
                Select::make('door_model_id')
                    ->label('Model dveří')
                    ->relationship('doorModel', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('door_variant_id')
                    ->label('Varianta dveří')
                    ->relationship('doorVariant', 'code')
                    ->searchable()
                    ->preload(),
                Select::make('decor_id')
                    ->label('Dekor')
                    ->relationship('decor', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('glass_type_id')
                    ->label('Sklo')
                    ->relationship('glassType', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('surcharges')
                    ->label('Příplatky')
                    ->relationship('surcharges', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Přiřazení příplatků. Ceny v pivot tabulce zůstávají tak, jak přišly z poptávky.'),
            ],
            'price' => [
                TextInput::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->helperText('Cena bez DPH v Kč. Ruční úprava nic nepřepočítává, upravte i cenu s DPH.')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01'),
                TextInput::make('price_with_vat')
                    ->label('Cena s DPH')
                    ->helperText('Stejná cena včetně DPH, jak ji viděl zákazník v konfigurátoru.')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required()
                    ->default(0)
                    ->step('0.01'),
                Text::make(fn (?Inquiry $record): ?string => self::computedTotals($record))
                    ->color('gray')
                    ->visible(fn (?Inquiry $record): bool => self::computedTotals($record) !== null),
            ],
            'preview' => [
                Image::make(
                    fn (?Inquiry $record): string => self::preview($record)['url'] ?? '',
                    fn (?Inquiry $record): string => self::preview($record)['label'] ?? 'Náhled dveří',
                )
                    ->imageHeight('26rem')
                    ->visible(fn (?Inquiry $record): bool => self::preview($record) !== null),
                Text::make(fn (?Inquiry $record): string => self::previewCaption($record))
                    ->color('gray')
                    ->visible(fn (?Inquiry $record): bool => self::preview($record) !== null),
                Text::make('K této poptávce není uložený žádný obrázek. Novější poptávky si nesou vygenerovaný náhled z konfigurátoru; u starších doplňte referenční obrázek u varianty dveří nebo náhled u modelu, dekoru či skla.')
                    ->color('gray')
                    ->visible(fn (?Inquiry $record): bool => self::preview($record) === null),
            ],
        ];
    }

    /**
     * The best available image of the configured door, with the note which image it is.
     *
     * @param  list<Component>  $components
     */
    private static function previewSection(array $components): Section
    {
        return Section::make('Náhled vybraných dveří')
            ->description('Konfigurátor složí při odeslání poptávky zvolené dveře do jednoho obrázku (rám, křídlo, dekor, sklo a overlaye, bez místnosti a bez osobních údajů) a obrázek se uloží k poptávce. Zobrazuje se přednostně; starší poptávky bez vygenerovaného obrázku spadají na referenční obrázek varianty, jinak na náhled modelu, dekoru nebo skla.')
            ->schema($components)
            ->columns(1)
            ->columnSpanFull();
    }

    /**
     * The read only snapshot of the configuration as it was sent from the configurator.
     */
    private static function snapshotSection(): Section
    {
        return Section::make('Uložená konfigurace z konfigurátoru')
            ->description('Historický snapshot z odeslání poptávky: názvy, kódy a ceny, jak platily v okamžiku odeslání. Needitovatelný, zákazník ho viděl právě takto.')
            ->schema(fn (?Inquiry $record): array => self::snapshotLines($record))
            ->collapsible()
            ->columns(1)
            ->columnSpanFull();
    }

    /**
     * @return array<int, Component>
     */
    private static function snapshotLines(?Inquiry $record): array
    {
        $lines = app(InquirySnapshotPresenter::class)->lines($record?->configuration);

        if ($lines === []) {
            return [
                Text::make('Poptávka nemá uloženou konfiguraci.'),
            ];
        }

        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->schema(array_map(
                    fn (array $line): Group => Group::make([
                        Text::make($line['label'])->color('gray'),
                        Text::make($line['value']),
                    ]),
                    $lines,
                )),
        ];
    }

    /**
     * @return array{url: string, label: string, caption: string}|null
     */
    private static function preview(?Inquiry $record): ?array
    {
        return app(InquiryPreviewResolver::class)->resolve($record);
    }

    private static function previewCaption(?Inquiry $record): string
    {
        $preview = self::preview($record);

        if ($preview === null) {
            return '';
        }

        return 'Zobrazeno: '.$preview['label'].'. '.$preview['caption'];
    }

    private static function computedTotals(?Inquiry $record): ?string
    {
        return app(InquirySnapshotPresenter::class)->computedTotals($record?->configuration);
    }

    private static function inquiryNumber(?Inquiry $record): string
    {
        return $record === null ? 'Nová poptávka' : 'Poptávka č. '.$record->getKey();
    }

    private static function receivedLine(?Inquiry $record): string
    {
        if ($record?->created_at === null) {
            return '';
        }

        return 'Přijata '.$record->created_at->format('d.m.Y H:i');
    }

    private static function anonymizedLine(?Inquiry $record): ?string
    {
        if ($record?->anonymized_at === null) {
            return null;
        }

        return 'Osobní údaje byly anonymizovány '.$record->anonymized_at->format('d.m.Y H:i')
            .'. Konfigurace a ceny zůstávají zachované.';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Zákazník')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doorModel.name')
                    ->label('Model dveří')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doorVariant.code')
                    ->label('Varianta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InquiryStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => InquiryStatus::tryFrom($state)?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('anonymized_at')
                    ->label('Anonymizováno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('ne')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price_without_vat')
                    ->label('Cena bez DPH')
                    ->money('CZK', divideBy: 1)
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price_with_vat')
                    ->label('Cena s DPH')
                    ->money('CZK', divideBy: 1)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInquiries::route('/'),
            'edit' => EditInquiry::route('/{record}/edit'),
        ];
    }
}
