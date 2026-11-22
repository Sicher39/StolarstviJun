<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InquiryResource\Pages\EditInquiry;
use App\Filament\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\Decor;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Hardware;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Services\Inquiry\InquiryItemBuilder;
use App\Services\Inquiry\InquiryItemSelectionResolver;
use App\Services\Inquiry\InquiryPreviewResolver;
use App\Services\Inquiry\InquirySnapshotPresenter;
use App\Services\Inquiry\InquiryStatus;
use App\Services\Inquiry\InquiryTotalsCalculator;
use App\Services\VatCalculator;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Inquiries sent from the configurator, edited as one record in two columns: the customer on the left,
 * the doors of the request and their prices below.
 *
 * A request may carry several separately configured doors. They are edited one by one in the items
 * repeater, each with its own name, quantity, catalog selection, price per piece and the resulting line
 * price; the totals of the inquiry are then recomputed from the items on every save (see
 * InquiryTotalsCalculator), never typed by hand. An inquiry stored before the items existed keeps the
 * single door view instead: its four catalog selects and its two prices stay editable exactly as before.
 *
 * The frame and the hardware of the „Ostatní“ step belong to an item as well: they are picked from the
 * accessories the chosen model offers and the price per piece is recalculated with them. The columns of
 * `inquiries` carry no such reference, so the single door view has nothing to bind them to and only says
 * where the stored values are kept.
 *
 * The `configuration` and `crm_payload` JSON snapshots are never edited here. `configuration` is shown
 * as read only text through InquirySnapshotPresenter, because it is the historical record of what the
 * customer saw. The item rows therefore keep their own catalog columns, so correcting a quote never
 * rewrites what was sent.
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
                            Section::make('Příplatky poptávky')
                                ->description('Příplatky přiřazené k poptávce. Ceny v pivot tabulce zůstávají tak, jak přišly z poptávky; cenu za kus počítá u každé položky tlačítko „Přepočítat z katalogu“.')
                                ->schema($fields['surcharges'])
                                ->columns(1),
                        ])->columnSpan(1),
                        Group::make([
                            Section::make('Vybraná konfigurace')
                                ->description('Volby z konfigurátoru. Jejich změna ovlivní jen tuto poptávku, katalog dveří zůstává beze změny.')
                                ->schema($fields['configuration'])
                                ->columns(1)
                                ->visible(fn (?Inquiry $record): bool => ! self::hasItems($record)),
                            Section::make('Cena')
                                ->description('Cena uložená u poptávky v Kč. Obě hodnoty jdou ručně upravit, kontrolujte je proto společně.')
                                ->schema($fields['price'])
                                ->columns(1)
                                ->visible(fn (?Inquiry $record): bool => ! self::hasItems($record)),
                            Section::make('Poptávka s více položkami')
                                ->description('Tato poptávka obsahuje samostatně nakonfigurované položky. Jejich volby, množství a ceny se upravují níže v části „Položky poptávky“; cena poptávky je součtem položek a přepočítá se při každém uložení.')
                                ->schema([
                                    Text::make(fn (?Inquiry $record): string => self::itemsSummaryLine($record))
                                        ->color('gray'),
                                ])
                                ->columns(1)
                                ->visible(fn (?Inquiry $record): bool => self::hasItems($record)),
                        ])->columnSpan(1),
                        self::itemsSection($fields['items']),
                        self::previewSection($fields['preview']),
                        self::recapSection(),
                        self::sentItemsSection(),
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
                // The single door view of an inquiry stored before the items existed. For a multi item
                // request the same selection is edited per item, so these fields are hidden on the page.
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
                // The frame and the hardware are stored on the items, so this view can not offer them as
                // selects; it says where the stored values are instead of inventing a field for a column
                // that the schema does not have.
                Text::make('Zárubeň a kování se ukládají u každé položky poptávky, proto je tato část nenabízí k úpravě. Jejich uložené hodnoty najdete níže v části „Uložená konfigurace z konfigurátoru“.')
                    ->color('gray')
                    ->columnSpanFull(),
            ],
            'surcharges' => [
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
                    ->helperText('Cena bez DPH v Kč. U poptávky bez položek se nic nepřepočítává, upravte i cenu s DPH.')
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
            'items' => [
                self::itemsRepeater(),
            ],
        ];
    }

    /**
     * The separately configured doors of the request: one repeater item each, saved through the `items`
     * relationship.
     *
     * The generated PNG of an item is composed by the browser at submit time and is never recreated
     * here; the block therefore shows what the catalog offers for the selection the form currently
     * holds, so a changed selection can not silently keep an unrelated picture. The quantity and the
     * price per piece are the only numbers the form sends - the line price is recomputed from them.
     */
    private static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->hiddenLabel()
            ->relationship()
            ->orderColumn('position')
            ->maxItems(InquiryItem::MAX_ITEMS_PER_INQUIRY)
            ->addActionLabel('Přidat položku')
            ->cloneable()
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::itemLabel($state))
            ->mutateRelationshipDataBeforeFillUsing(
                fn (array $data): array => app(InquiryItemSelectionResolver::class)->withSnapshotSelection($data),
            )
            ->extraItemActions([
                self::recalculateItemAction(),
            ])
            ->schema(self::itemFields())
            ->columns(['default' => 1, 'md' => 2]);
    }

    /**
     * The editable fields of one item: its name, its quantity, its catalog selection and its prices.
     *
     * The frame and the hardware of the „Ostatní“ step are chosen here as well, from the active
     * accessories of the model the item currently holds, so a quote can be corrected with the same
     * catalog the configurator offers. Public like `formFields()`, so the layout of an item can be
     * asserted without booting a panel.
     *
     * @return list<Component>
     */
    public static function itemFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Název položky')
                ->required()
                ->maxLength(160)
                ->columnSpanFull(),
            TextInput::make('quantity')
                ->label('Množství (ks)')
                ->helperText('Počet kusů této konfigurace. Nejvýše '.InquiryItem::MAX_QUANTITY_PER_ITEM.' kusů na jednu položku.')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(InquiryItem::MAX_QUANTITY_PER_ITEM)
                ->default(1)
                ->required()
                ->live(onBlur: true),
            TextInput::make('unit_price_without_vat')
                ->label('Cena za kus bez DPH')
                ->helperText('Cena za kus v Kč. Tlačítko „Přepočítat z katalogu“ ji nastaví podle aktuálního ceníku.')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->default(0)
                ->required()
                ->live(onBlur: true),
            Select::make('door_model_id')
                ->label('Model dveří')
                ->options(fn (Get $get): array => self::doorModelOptions($get('door_model_id')))
                ->searchable()
                ->preload()
                ->live()
                ->required(),
            Select::make('door_variant_id')
                ->label('Varianta dveří')
                ->helperText('Nabízejí se pouze varianty zvoleného modelu.')
                ->options(fn (Get $get): array => self::doorVariantOptions($get('door_model_id'), $get('door_variant_id')))
                ->searchable()
                ->preload()
                ->live()
                ->required()
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $doorModelId = self::intOrNull($get('door_model_id'));

                        if (blank($value) || $doorModelId === null) {
                            return;
                        }

                        if (! DoorVariant::query()->whereKey($value)->where('door_model_id', $doorModelId)->exists()) {
                            $fail('Vybraná varianta nepatří ke zvolenému modelu.');
                        }
                    },
                ]),
            Select::make('decor_id')
                ->label('Dekor')
                ->helperText('Nabízejí se pouze dekory přiřazené zvolenému modelu.')
                ->options(fn (Get $get): array => self::decorOptions($get('door_model_id'), $get('decor_id')))
                ->searchable()
                ->preload()
                ->live(),
            Select::make('glass_type_id')
                ->label('Sklo')
                ->helperText('Nabízejí se pouze skla přiřazená zvolenému modelu. Varianta bez skla sklo nepoužívá.')
                ->options(fn (Get $get): array => self::glassTypeOptions($get('door_model_id'), $get('glass_type_id')))
                ->searchable()
                ->preload()
                ->live(),
            // The frame and the hardware are optional, exactly like in the configurator: a request without
            // them is priced as before. Only an active accessory of the chosen model can be saved here.
            Select::make('door_frame_id')
                ->label('Zárubeň')
                ->helperText('Nabízejí se pouze aktivní zárubně přiřazené zvolenému modelu. Zárubeň vstupuje do ceny za kus.')
                ->options(fn (Get $get): array => self::doorFrameOptions($get('door_model_id'), $get('door_frame_id')))
                ->searchable()
                ->preload()
                ->live()
                ->rules([
                    self::accessoryRule(DoorFrame::class, 'Vybraná zárubeň není aktivní nebo nepatří ke zvolenému modelu.'),
                ]),
            Select::make('hardware_id')
                ->label('Kování')
                ->helperText('Nabízejí se pouze aktivní kování přiřazená zvolenému modelu. Kování vstupuje do ceny za kus.')
                ->options(fn (Get $get): array => self::hardwareOptions($get('door_model_id'), $get('hardware_id')))
                ->searchable()
                ->preload()
                ->live()
                ->rules([
                    self::accessoryRule(Hardware::class, 'Vybrané kování není aktivní nebo nepatří ke zvolenému modelu.'),
                ]),
            Text::make(fn (Get $get): string => self::itemTotalsText($get('quantity'), $get('unit_price_without_vat')))
                ->color('gray')
                ->columnSpanFull(),
            Image::make(
                fn (Get $get): string => self::selectionPreview($get)['url'] ?? '',
                fn (Get $get): string => self::selectionPreview($get)['label'] ?? 'Náhled volby',
            )
                ->imageHeight('22rem')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => self::selectionPreview($get) !== null),
            Text::make('Ke zvolené kombinaci není v katalogu žádný obrázek. Vygenerovaný náhled položky vzniká v prohlížeči při odeslání poptávky a v administraci se nepřekresluje.')
                ->color('gray')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => self::selectionPreview($get) === null),
        ];
    }

    /**
     * Recalculates the price per piece of one item from the current catalog, so a quote can follow a
     * price change without retyping the numbers. The formula is the one the configurator uses.
     *
     * Only the ids of the item the form currently holds are read here - never a price - and every amount
     * is resolved from the catalog by InquiryItemBuilder. An accessory the chosen model does not offer
     * adds nothing, exactly like a decor or a glass that the model lost, so the administration can price
     * a quote whose catalog changed in the meantime.
     */
    private static function recalculateItemAction(): Action
    {
        return Action::make('recalculateFromCatalog')
            ->label('Přepočítat z katalogu')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->tooltip('Nastaví cenu za kus podle modelu, varianty, dekoru, skla, zárubně, kování a příplatků z katalogu.')
            ->action(function (array $arguments, Repeater $component, InquiryItemBuilder $builder): void {
                $key = $arguments['item'] ?? null;

                if (! is_string($key)) {
                    return;
                }

                $state = $component->getRawItemState($key) ?? [];

                $unitPrice = $builder->catalogUnitPrice(
                    self::intOrNull($state['door_model_id'] ?? null),
                    self::intOrNull($state['door_variant_id'] ?? null),
                    self::intOrNull($state['decor_id'] ?? null),
                    self::intOrNull($state['glass_type_id'] ?? null),
                    self::surchargeIdsOfItem($key),
                    self::intOrNull($state['door_frame_id'] ?? null),
                    self::intOrNull($state['hardware_id'] ?? null),
                );

                if ($unitPrice === null) {
                    Notification::make()
                        ->warning()
                        ->title('Cenu se nepodařilo spočítat')
                        ->body('Nejprve vyberte model a variantu dveří, ze kterých se cena počítá.')
                        ->send();

                    return;
                }

                $items = $component->getState();
                $items = is_array($items) ? $items : [];
                $items[$key] = [...($items[$key] ?? []), 'unit_price_without_vat' => $unitPrice];

                $component->state($items);

                Notification::make()
                    ->success()
                    ->title('Cena za kus byla přepočítána z katalogu')
                    ->body('Cena celkem i rekapitulace poptávky se přepočítají při uložení.')
                    ->send();
            });
    }

    /**
     * The surcharges of one item, taken from the snapshot of an already stored item.
     *
     * The pivot table holds the surcharges of the whole inquiry, so the item keeps its own ones in the
     * snapshot. A pending item has none yet, which is why the recalculation of a new item prices the
     * catalog selection only.
     *
     * @return list<int>
     */
    private static function surchargeIdsOfItem(string $itemKey): array
    {
        if (! str_starts_with($itemKey, 'record-')) {
            return [];
        }

        $item = InquiryItem::query()->find((int) substr($itemKey, strlen('record-')));

        return $item instanceof InquiryItem ? $item->snapshotSurchargeIds() : [];
    }

    /**
     * The rule of one accessory select: an id may only be saved when the catalog still offers it for the
     * model the item currently holds.
     *
     * The options of the select already hide the other rows, but a dependent select is a convenience and
     * not a guarantee - a stored value and a changed model both reach the form without passing through
     * the options. The rule therefore repeats the check of InquiryItemBuilder, and it is spelled out
     * instead of silently dropping the value, so the administrator sees why an item can not be saved.
     * Without a model nothing can be decided and the required model field reports itself.
     *
     * @param  class-string<DoorFrame|Hardware>  $accessory
     */
    private static function accessoryRule(string $accessory, string $message): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $accessory, $message): void {
            $doorModelId = self::intOrNull($get('door_model_id'));

            if (blank($value) || $doorModelId === null) {
                return;
            }

            $isOffered = $accessory::query()
                ->whereKey($value)
                ->where('active', true)
                ->whereHas('doorModels', fn ($query) => $query->whereKey($doorModelId))
                ->exists();

            if (! $isOffered) {
                $fail($message);
            }
        };
    }

    /**
     * The heading of one repeater item: the name the workshop gave it, otherwise the catalog label of
     * the selection it holds.
     *
     * @param  array<string, mixed>  $state
     */
    private static function itemLabel(array $state): ?string
    {
        $name = trim((string) ($state['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $quantity = is_numeric($state['quantity'] ?? null) ? (int) $state['quantity'] : 1;

        return 'Nová položka ('.$quantity.' ks)';
    }

    /**
     * The single door image of the inquiry, which describes its first item for a multi item request.
     *
     * @param  list<Component>  $components
     */
    private static function previewSection(array $components): Section
    {
        return Section::make('Náhled vybraných dveří')
            ->description('Konfigurátor složí při odeslání poptávky zvolené dveře do jednoho obrázku (rám, křídlo, dekor, sklo a overlaye, bez místnosti a bez osobních údajů) a obrázek se uloží k poptávce. Zobrazuje se přednostně; starší poptávky bez vygenerovaného obrázku spadají na referenční obrázek varianty, jinak na náhled modelu, dekoru nebo skla.')
            ->schema($components)
            ->columns(1)
            ->columnSpanFull()
            ->visible(fn (?Inquiry $record): bool => ! self::hasItems($record));
    }

    /**
     * The read only snapshot of the configuration as it was sent from the configurator.
     *
     * It is hidden for a request whose snapshot already lists its items, because those are described
     * one by one in the "Odeslané položky" section. An inquiry stored before the items existed keeps
     * this view, even after the workshop added items to it: the snapshot still describes the single door
     * the customer sent.
     */
    private static function snapshotSection(): Section
    {
        return Section::make('Uložená konfigurace z konfigurátoru')
            ->description('Historický snapshot z odeslání poptávky: názvy, kódy a ceny, jak platily v okamžiku odeslání. Needitovatelný, zákazník ho viděl právě takto.')
            ->schema(fn (?Inquiry $record): array => self::snapshotLines($record))
            ->collapsible()
            ->columns(1)
            ->columnSpanFull()
            ->visible(fn (?Inquiry $record): bool => ! self::hasItems($record) || ! self::snapshotListsItems($record));
    }

    /**
     * True when the stored snapshot describes the whole request as a list of items, which is the shape
     * the configurator writes since an inquiry may carry several doors.
     */
    private static function snapshotListsItems(?Inquiry $record): bool
    {
        $configuration = $record?->configuration;

        return is_array($configuration) && is_array($configuration['items'] ?? null);
    }

    /**
     * The editable doors of the request.
     *
     * @param  list<Component>  $components
     */
    private static function itemsSection(array $components): Section
    {
        return Section::make('Položky poptávky')
            ->description('Každá položka je samostatně nakonfigurované dveře s vlastním názvem, množstvím a cenou za kus. Cena celkem je cena za kus násobená množstvím a při uložení se z položek přepočítá i cena celé poptávky. Snapshot z odeslání zůstává nedotčený.')
            ->schema($components)
            ->columns(1)
            ->columnSpanFull();
    }

    /**
     * The recap of the whole request: the number of doors and the three price levels.
     *
     * The sum is shown next to the recap the customer saw, and the price of every single item is explained
     * in the "Odeslané položky" section below, so a higher total always has a visible cause.
     */
    private static function recapSection(): Section
    {
        return Section::make('Rekapitulace poptávky')
            ->description('Součet všech položek: celkový počet dveří a cena bez DPH, DPH a cena s DPH. Uloží se při každém uložení poptávky, ručně se needituje. Rozpis ceny jednotlivých položek je níže v části „Odeslané položky“.')
            ->schema(fn (?Inquiry $record): array => self::recapBlocks($record))
            ->columns(1)
            ->columnSpanFull();
    }

    /**
     * The doors of the request as they were sent, with the snapshot and the generated image of each
     * item. Nothing here is editable; it is the record the customer saw.
     */
    private static function sentItemsSection(): Section
    {
        return Section::make('Odeslané položky')
            ->description('Co zákazník odeslal: rozpis ceny za kus, množství, ceny a volby jednotlivých položek v okamžiku odeslání, včetně vygenerovaného náhledu. Snapshot se nikdy nepřepisuje, takže slouží k porovnání s aktuálně uloženou úpravou výše.')
            ->schema(fn (?Inquiry $record): array => self::itemBlocks($record))
            ->collapsible()
            ->columns(1)
            ->columnSpanFull()
            ->visible(fn (?Inquiry $record): bool => self::hasItems($record));
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

        return [self::linesGrid($lines)];
    }

    /**
     * @return array<int, Component>
     */
    private static function itemBlocks(?Inquiry $record): array
    {
        if ($record === null) {
            return [
                Text::make('Položky se zobrazí po uložení poptávky.'),
            ];
        }

        $items = $record->items;

        if ($items->isEmpty()) {
            return [
                Text::make('Poptávka obsahuje jednu položku z dřívější verze konfigurátoru. Její konfiguraci a ceny najdete níže v uloženém snapshotu.')
                    ->color('gray'),
            ];
        }

        $presenter = app(InquirySnapshotPresenter::class);
        $resolver = app(InquiryPreviewResolver::class);
        $blocks = [];

        foreach ($items->values() as $index => $item) {
            if (! $item instanceof InquiryItem) {
                continue;
            }

            $preview = $resolver->resolveItem($item);
            $snapshot = $item->snapshot();
            // The stored row may already carry a recalculated price; the snapshot stays the record of what
            // the customer was quoted, so the two views are printed one after another and never merged.
            $priceComparison = $presenter->storedPriceComparison($snapshot, [
                'quantity' => $item->quantity,
                'unit_price_without_vat' => $item->unit_price_without_vat,
                'price_without_vat' => $item->price_without_vat,
                'price_with_vat' => $item->price_with_vat,
            ]);

            $blocks[] = Group::make([
                Text::make(($index + 1).'. '.$item->name)
                    ->badge()
                    ->color('primary'),
                Text::make('Rozpis ceny za kus při odeslání (bez DPH)')
                    ->color('gray'),
                self::linesGrid($presenter->priceBreakdownLines($snapshot)),
                self::linesGrid($presenter->itemPriceLines($snapshot)),
                Text::make(fn (): ?string => $priceComparison)
                    ->color('warning')
                    ->visible(fn (): bool => $priceComparison !== null),
                Text::make('Volby při odeslání')
                    ->color('gray'),
                self::linesGrid($presenter->itemLines($snapshot)),
                Image::make(
                    fn (): string => $preview['url'] ?? '',
                    fn (): string => $preview['label'] ?? 'Náhled položky',
                )
                    ->imageHeight('22rem')
                    ->visible(fn (): bool => $preview !== null),
                Text::make('K této položce není uložený vygenerovaný náhled.')
                    ->color('gray')
                    ->visible(fn (): bool => $preview === null),
            ]);
        }

        return $blocks;
    }

    /**
     * @return array<int, Component>
     */
    private static function recapBlocks(?Inquiry $record): array
    {
        if ($record === null) {
            return [
                Text::make('Rekapitulace se zobrazí po uložení poptávky.'),
            ];
        }

        $presenter = app(InquirySnapshotPresenter::class);
        $blocks = [];

        if (self::hasItems($record)) {
            $blocks[] = Text::make('Součet položek, který se uloží do ceny poptávky')
                ->color('gray');
            $blocks[] = self::linesGrid($presenter->totalsLines(app(InquiryTotalsCalculator::class)->summary($record)));
        }

        $sentLines = $presenter->summaryLines($record->configuration);

        if ($sentLines !== []) {
            $blocks[] = Text::make('Rekapitulace, kterou viděl zákazník v konfigurátoru')
                ->color('gray');
            $blocks[] = self::linesGrid($sentLines);
        }

        if ($blocks === []) {
            // An inquiry stored without a snapshot has no recap to show. The editable price columns stay
            // the source of truth, so only the number of doors is added from the stored items.
            return [
                Text::make('Celkem dveří: '.$record->totalDoors().' ks'),
                Text::make('Poptávka nemá uloženou rekapitulaci. Ceny najdete výše v části Cena.')
                    ->color('gray'),
            ];
        }

        return $blocks;
    }

    /**
     * A two column grid of label and value pairs, shared by every read only block of the record.
     *
     * @param  list<array{label: string, value: string}>  $lines
     */
    private static function linesGrid(array $lines): Component
    {
        if ($lines === []) {
            return Text::make('Snapshot není uložený.')
                ->color('gray');
        }

        return Grid::make(['default' => 1, 'md' => 2])
            ->schema(array_map(
                fn (array $line): Group => Group::make([
                    Text::make($line['label'])->color('gray'),
                    Text::make($line['value']),
                ]),
                $lines,
            ));
    }

    /**
     * The number of items and doors of the request, used by the note that replaces the single door
     * fields of a multi item inquiry.
     */
    private static function itemsSummaryLine(?Inquiry $record): string
    {
        if ($record === null) {
            return '';
        }

        $record->loadMissing('items');

        return $record->items->count().' položek · '.$record->totalDoors().' ks dveří. Konfiguraci, množství a ceny upravte níže.';
    }

    /**
     * True when the request is described by item rows. An inquiry sent before the items existed has no
     * rows here and keeps the single door view with its own price fields.
     */
    private static function hasItems(?Inquiry $record): bool
    {
        if (! $record instanceof Inquiry) {
            return false;
        }

        $record->loadMissing('items');

        return $record->items->isNotEmpty();
    }

    /**
     * @return array{url: string, label: string, caption: string}|null
     */
    private static function preview(?Inquiry $record): ?array
    {
        return app(InquiryPreviewResolver::class)->resolve($record);
    }

    /**
     * The catalog image of the selection one repeater item currently holds.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    private static function selectionPreview(Get $get): ?array
    {
        return app(InquiryPreviewResolver::class)->resolveSelection(
            self::intOrNull($get('door_model_id')),
            self::intOrNull($get('door_variant_id')),
            self::intOrNull($get('decor_id')),
            self::intOrNull($get('glass_type_id')),
        );
    }

    private static function previewCaption(?Inquiry $record): string
    {
        $preview = self::preview($record);

        if ($preview === null) {
            return '';
        }

        return 'Zobrazeno: '.$preview['label'].'. '.$preview['caption'];
    }

    /**
     * The line prices of one edited item, computed the same way the totals calculator does, so the
     * effect of a quantity change is visible before the inquiry is saved.
     */
    private static function itemTotalsText(mixed $quantity, mixed $unitPriceWithoutVat): string
    {
        if (! is_numeric($quantity) || ! is_numeric($unitPriceWithoutVat)) {
            return 'Cena celkem se dopočítá ze zadaného množství a ceny za kus při uložení.';
        }

        $linePriceWithoutVat = round((float) $unitPriceWithoutVat * (int) $quantity, 2);

        return 'Cena celkem bez DPH '.self::money($linePriceWithoutVat)
            .' · s DPH '.self::money(VatCalculator::applyRate($linePriceWithoutVat));
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ').' Kč';
    }

    /**
     * Models offered by the catalog, including the stored one so a model that was taken out of the
     * offer does not make an old inquiry impossible to save.
     *
     * @return array<int, string>
     */
    private static function doorModelOptions(mixed $currentId): array
    {
        $options = DoorModel::query()->orderBy('name')->pluck('name', 'id')->all();
        $currentId = self::intOrNull($currentId);

        if ($currentId === null || array_key_exists($currentId, $options)) {
            return $options;
        }

        $current = DoorModel::query()->find($currentId);

        return $current === null
            ? $options
            : [$currentId => $current->name.' (mimo aktuální nabídku)'] + $options;
    }

    /**
     * Variants of the chosen model. The constraint mirrors InquiryItemBuilder, so a pair that the
     * configurator would refuse can not be stored by the administration either.
     *
     * @return array<int, string>
     */
    private static function doorVariantOptions(mixed $doorModelId, mixed $currentId): array
    {
        $doorModelId = self::intOrNull($doorModelId);
        $currentId = self::intOrNull($currentId);

        // Without a model the select offers the whole catalog, otherwise the options of a stored variant
        // would lose their label before a model is picked. The pair itself is checked by the rule on the
        // field, so a variant of another model can not be saved.
        $options = DoorVariant::query()
            ->when($doorModelId !== null, fn ($query) => $query->where('door_model_id', $doorModelId))
            ->orderBy('code')
            ->pluck('code', 'id')
            ->all();

        return self::withCurrentOption($options, $currentId, fn (): ?string => DoorVariant::query()->find($currentId)?->code);
    }

    /**
     * Active decors of the chosen model, plus the stored one.
     *
     * @return array<int, string>
     */
    private static function decorOptions(mixed $doorModelId, mixed $currentId): array
    {
        $doorModelId = self::intOrNull($doorModelId);
        $currentId = self::intOrNull($currentId);

        $options = $doorModelId === null
            ? Decor::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()
            : Decor::query()
                ->where('active', true)
                ->whereHas('doorModels', fn ($query) => $query->whereKey($doorModelId))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();

        return self::withCurrentOption($options, $currentId, fn (): ?string => Decor::query()->find($currentId)?->name);
    }

    /**
     * Active glass types of the chosen model, plus the stored one.
     *
     * @return array<int, string>
     */
    private static function glassTypeOptions(mixed $doorModelId, mixed $currentId): array
    {
        $doorModelId = self::intOrNull($doorModelId);
        $currentId = self::intOrNull($currentId);

        $options = $doorModelId === null
            ? GlassType::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()
            : GlassType::query()
                ->where('active', true)
                ->whereHas('doorModels', fn ($query) => $query->whereKey($doorModelId))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();

        return self::withCurrentOption($options, $currentId, fn (): ?string => GlassType::query()->find($currentId)?->name);
    }

    /**
     * Active frames offered for the chosen model, plus the stored one.
     *
     * The accessory decides which models may be configured with it, so the query goes through its
     * `doorModels` relation - the same one the configurator reads, which keeps the administration and
     * the browser on one catalog. Public like `formFields()`: the filtering has to be assertable
     * without booting a panel.
     *
     * @return array<int, string>
     */
    public static function doorFrameOptions(mixed $doorModelId, mixed $currentId = null): array
    {
        $doorModelId = self::intOrNull($doorModelId);
        $currentId = self::intOrNull($currentId);

        $options = DoorFrame::query()
            ->where('active', true)
            ->when(
                $doorModelId !== null,
                fn ($query) => $query->whereHas('doorModels', fn ($query) => $query->whereKey($doorModelId)),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return self::withCurrentOption($options, $currentId, fn (): ?string => DoorFrame::query()->find($currentId)?->name);
    }

    /**
     * Active hardware offered for the chosen model, plus the stored one. It follows the frame exactly:
     * both are accessories attached to the models from their own side.
     *
     * @return array<int, string>
     */
    public static function hardwareOptions(mixed $doorModelId, mixed $currentId = null): array
    {
        $doorModelId = self::intOrNull($doorModelId);
        $currentId = self::intOrNull($currentId);

        $options = Hardware::query()
            ->where('active', true)
            ->when(
                $doorModelId !== null,
                fn ($query) => $query->whereHas('doorModels', fn ($query) => $query->whereKey($doorModelId)),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return self::withCurrentOption($options, $currentId, fn (): ?string => Hardware::query()->find($currentId)?->name);
    }

    /**
     * Keeps a stored value selectable even when the catalog no longer offers it, so changing the offer
     * never turns an old inquiry into a form that refuses to save.
     *
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private static function withCurrentOption(array $options, ?int $currentId, Closure $currentLabel): array
    {
        if ($currentId === null || array_key_exists($currentId, $options)) {
            return $options;
        }

        $label = $currentLabel();

        return $label === null
            ? $options
            : [$currentId => $label.' (mimo aktuální nabídku)'] + $options;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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

    /**
     * The frame and the hardware of the request in one line, for the list table.
     *
     * The first item describes the request in the columns of the inquiry, so its stored references are
     * read first; an inquiry stored before the items (or before the accessory columns) existed keeps the
     * selection in the snapshot only, which is the fallback. Nothing is printed for a request with
     * neither, so an old record renders empty instead of inventing a selection. Public like
     * `formFields()`, so the label of a stored selection can be asserted without booting a panel.
     */
    public static function accessoryLabels(?Inquiry $record): ?string
    {
        if (! $record instanceof Inquiry) {
            return null;
        }

        // Both relations are loaded up front, so the label does not run a query per row of the table.
        $record->loadMissing('items.doorFrame', 'items.hardware');

        $firstItem = $record->items->first();
        $snapshot = $firstItem instanceof InquiryItem ? $firstItem->snapshot() : ($record->configuration ?? []);

        $labels = array_values(array_filter([
            self::accessoryLabel('Zárubeň', $firstItem?->doorFrame?->name, $snapshot['door_frame'] ?? null),
            self::accessoryLabel('Kování', $firstItem?->hardware?->name, $snapshot['hardware'] ?? null),
        ]));

        return $labels === [] ? null : implode(' · ', $labels);
    }

    /**
     * "Zárubeň: Bezfalcová" from the catalog reference of the item, or from the snapshot of a request
     * whose columns are still empty.
     */
    private static function accessoryLabel(string $kind, ?string $catalogName, mixed $snapshotSection): ?string
    {
        $name = $catalogName;

        if (! filled($name) && is_array($snapshotSection) && is_string($snapshotSection['name'] ?? null)) {
            $name = trim($snapshotSection['name']);
        }

        return filled($name) ? $kind.': '.$name : null;
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
                // The columns of the inquiry still describe the first item of a multi item request, so
                // the label says which door the value belongs to.
                TextColumn::make('doorModel.name')
                    ->label('Model dveří (1. položka)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doorVariant.code')
                    ->label('Varianta (1. položka)')
                    ->searchable()
                    ->sortable(),
                // The accessories are stored per item and in the snapshot, so the label is resolved from
                // the same door the two columns above describe.
                TextColumn::make('accessories')
                    ->label('Zárubeň a kování (1. položka)')
                    ->state(fn (Inquiry $record): ?string => self::accessoryLabels($record))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
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
