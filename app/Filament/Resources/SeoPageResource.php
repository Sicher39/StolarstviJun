<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeoPageResource\Pages\CreateSeoPage;
use App\Filament\Resources\SeoPageResource\Pages\EditSeoPage;
use App\Filament\Resources\SeoPageResource\Pages\ListSeoPages;
use App\Models\SeoPage;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Metadata of the public pages: the document head for search engines and the structured data that
 * answer engines read. Records are created once by the seeder and only edited afterwards, therefore
 * the resource offers no delete action at all - SeoPage also refuses permanent and bulk deletion.
 *
 * The form is split into the eight sections the content editors asked for (SEO, Social, Sociální
 * profily, Lokalita a dostupnost, Schema, AEO, Technické, Poznámky). The section names are the keys
 * of formSections(), so the panel and the acceptance test can never drift apart.
 *
 * Every field of the SEO brief is edited under its own column name (`canonical_url`,
 * `twitter_title`, `twitter_description`, `social_facebook_url`, `social_instagram_url`,
 * `social_linkedin_url`, `social_youtube_url`, `schema_type`, `aeo_summary`, `aeo_search_intent`,
 * `aeo_entities`, `business_name`, `street_address`, `address_locality`, `postal_code`,
 * `address_country`, `available_languages`, `latitude`, `longitude`, `offers_online`).
 *
 * Three columns never get an editable field: the single `og_image` column is filled either by an
 * upload or by an external URL, and `twitter_image` as well as the mirrors `route_name` and
 * `page_name` are derived from `og_image`, `key` and `label` by SeoPage on every save.
 *
 * The historical `locality` and the `social_profiles` repeater are no longer edited: their values
 * stay readable (they still feed the payload and the structured data), but new information is
 * written to `address_locality` and to the four explicit profile URLs.
 */
class SeoPageResource extends Resource
{
    protected static ?string $model = SeoPage::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'GDPR';

    protected static ?string $navigationLabel = 'SEO stránek';

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return 'SEO stránka';
    }

    public static function getPluralModelLabel(): string
    {
        return 'SEO stránky';
    }

    /**
     * The eight form sections, in the order the editors work through them.
     *
     * @return array<string, list<Component>>
     */
    public static function formSections(): array
    {
        return [
            'SEO' => [
                TextInput::make('seo_title')
                    ->label('Titulek stránky (title)')
                    ->helperText('Název firmy se doplní automaticky, pokud ho titulek už neobsahuje.')
                    ->required()
                    ->maxLength(255),
                Textarea::make('seo_description')
                    ->label('Popis stránky (meta description)')
                    ->helperText('Doporučená délka je 120 až 160 znaků.')
                    ->maxLength(320)
                    ->rows(3),
                TextInput::make('seo_keywords')
                    ->label('Klíčová slova')
                    ->helperText('Čárkou oddělený seznam, například: dveře na míru, vchodové dveře, stolařství Brno.')
                    ->maxLength(SeoPage::MAX_KEYWORDS_LENGTH),
                TextInput::make('canonical_url')
                    ->label('Kanonická URL (canonical_url)')
                    ->helperText('Celá adresa začínající https:// nebo cesta začínající /. Prázdné pole znamená, že se kanonická adresa odvodí z cesty stránky a z routy.')
                    ->maxLength(2048)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (blank($value)) {
                                return;
                            }

                            if (SeoPage::normalizeCanonicalUrl($value) === null) {
                                $fail('Zadejte celou adresu začínající na http:// nebo https://, nebo cestu začínající na /.');
                            }
                        },
                    ]),
            ],

            'Social' => [
                TextInput::make('og_title')
                    ->label('Titulek při sdílení')
                    ->helperText('Když zůstane prázdný, použije se titulek stránky.')
                    ->maxLength(255),
                Textarea::make('og_description')
                    ->label('Popis při sdílení')
                    ->helperText('Když zůstane prázdný, použije se popis stránky.')
                    ->maxLength(512)
                    ->rows(3),
                TextInput::make('twitter_title')
                    ->label('Titulek na Twitteru/X (twitter_title)')
                    ->helperText('Když zůstane prázdný, použije se titulek při sdílení.')
                    ->maxLength(255),
                Textarea::make('twitter_description')
                    ->label('Popis na Twitteru/X (twitter_description)')
                    ->helperText('Když zůstane prázdný, použije se popis při sdílení.')
                    ->maxLength(512)
                    ->rows(3),
                Select::make('og_type')
                    ->label('Typ obsahu (og:type)')
                    ->options(SeoPage::ogTypeOptions())
                    ->required()
                    ->default('website'),
                FileUpload::make('og_image')
                    ->label('Obrázek pro sdílení (nahrát soubor)')
                    ->helperText('Doporučený poměr stran je 1,91:1 (například 1200 × 630 px). Nahrává se na veřejný disk a používá se i pro náhled na Twitteru/X.')
                    ->image()
                    ->disk(SeoPage::OG_IMAGE_DISK)
                    ->directory(SeoPage::OG_IMAGE_DIRECTORY)
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120),
                TextInput::make('og_image_external_url')
                    ->label('Obrázek pro sdílení (URL)')
                    ->helperText('Veřejná adresa obrázku, pokud není nahraný soubor. Uloží se do stejného pole jako nahraný soubor.')
                    ->url()
                    ->maxLength(2048)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (blank($value)) {
                                return;
                            }

                            if (SeoPage::normalizeUrl($value) === null) {
                                $fail('Zadejte celou adresu obrázku začínající na http:// nebo https://.');
                            }
                        },
                    ]),
            ],

            'Sociální profily' => [
                TextInput::make('social_facebook_url')
                    ->label('Facebook (social_facebook_url)')
                    ->helperText('Celá adresa profilu. Prázdné pole převezme hodnotu z globálního záznamu.')
                    ->url()
                    ->maxLength(512),
                TextInput::make('social_instagram_url')
                    ->label('Instagram (social_instagram_url)')
                    ->helperText('Celá adresa profilu. Prázdné pole převezme hodnotu z globálního záznamu.')
                    ->url()
                    ->maxLength(512),
                TextInput::make('social_linkedin_url')
                    ->label('LinkedIn (social_linkedin_url)')
                    ->helperText('Celá adresa profilu. Prázdné pole převezme hodnotu z globálního záznamu.')
                    ->url()
                    ->maxLength(512),
                TextInput::make('social_youtube_url')
                    ->label('YouTube (social_youtube_url)')
                    ->helperText('Celá adresa profilu. Prázdné pole převezme hodnotu z globálního záznamu.')
                    ->url()
                    ->maxLength(512),
            ],

            'Lokalita a dostupnost' => [
                TextInput::make('business_name')
                    ->label('Název firmy (business_name)')
                    ->helperText('Název, který se používá ve strukturovaných datech, například Stolařství Jun a synové s.r.o.')
                    ->maxLength(255),
                TextInput::make('street_address')
                    ->label('Ulice a číslo (street_address)')
                    ->maxLength(255),
                TextInput::make('address_locality')
                    ->label('Město (address_locality)')
                    ->helperText('Místo, kde působíte, například Zastávka u Brna. Prázdné pole převezme hodnotu z globálního záznamu.')
                    ->maxLength(120),
                TextInput::make('postal_code')
                    ->label('PSČ (postal_code)')
                    ->maxLength(16),
                TextInput::make('address_country')
                    ->label('Země (address_country)')
                    ->helperText('Kód země, například CZ.')
                    ->maxLength(64),
                TextInput::make('latitude')
                    ->label('Zeměpisná šířka (latitude)')
                    ->helperText('Například 49.1833333. Prázdné pole znamená, že souřadnice na webu neuvádíme.')
                    ->numeric()
                    ->maxLength(12),
                TextInput::make('longitude')
                    ->label('Zeměpisná délka (longitude)')
                    ->helperText('Například 16.3500000. Prázdné pole znamená, že souřadnice na webu neuvádíme.')
                    ->numeric()
                    ->maxLength(12),
                TagsInput::make('available_languages')
                    ->label('Podporované jazyky (available_languages)')
                    ->helperText('Kódy jazyků, ve kterých firmě rozumíte, například cs a en.'),
                Toggle::make('offers_online')
                    ->label('Objednávka online (offers_online)')
                    ->helperText('Zapněte, když je možné poptat nebo objednat z webu. Používá se ve strukturovaných datech.')
                    ->default(false),
                TextInput::make('area_served')
                    ->label('Oblasti působnosti')
                    ->helperText('Odkud jezdíte za zakázkami, například Brno a okolí. Používá se v lokalizačních strukturovaných datech.')
                    ->maxLength(255),
                TextInput::make('availability')
                    ->label('Dostupnost')
                    ->helperText('Například: Po–Pá 7:00–16:00, jinak po domluvě. Prázdné pole znamená, že dostupnost na webu neuvádíme.')
                    ->maxLength(255),
            ],

            'Schema' => [
                TextInput::make('schema_type')
                    ->label('Typ strukturovaných dat (schema_type)')
                    ->helperText('Typ podle schema.org, například WebPage, CollectionPage, ContactPage, Organization nebo Carpenter. Použije se, když je pole JSON-LD prázdné.')
                    ->maxLength(64),
                Textarea::make('schema_json')
                    ->label('JSON-LD')
                    ->helperText('Objekt nebo seznam objektů schema.org. Když pole necháte prázdné, sestaví se uzel z polí schema_type, adresy, souřadnic, jazyků a entit. Adresa webu a sociální profily se doplní automaticky.')
                    ->rows(12)
                    ->columnSpanFull()
                    // The state is a JSON string while the column is cast to an array, so the
                    // editor works with readable JSON instead of an escaped value.
                    ->formatStateUsing(fn (mixed $state): ?string => is_array($state) && $state !== []
                        ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        : null)
                    ->dehydrateStateUsing(function (mixed $state): ?array {
                        if (! is_string($state) || trim($state) === '') {
                            return null;
                        }

                        $decoded = json_decode($state, true);

                        return is_array($decoded) && $decoded !== [] ? $decoded : null;
                    })
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value) || trim($value) === '') {
                                return;
                            }

                            json_decode($value);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $fail('Zadejte platný JSON, například {"@context": "https://schema.org", "@type": "WebPage"}.');
                            }
                        },
                    ]),
            ],

            'AEO' => [
                Textarea::make('aeo_summary')
                    ->label('Shrnutí pro AI odpovědi (aeo_summary)')
                    ->helperText('Několik vět, které vystihují stránku a dají se citovat samostatně.')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('aeo_search_intent')
                    ->label('Záměr hledání (aeo_search_intent)')
                    ->helperText('Co návštěvník na stránce řeší, například: transakční – hledá cenu dveří na míru.')
                    ->maxLength(255),
                TagsInput::make('aeo_entities')
                    ->label('Entity stránky (aeo_entities)')
                    ->helperText('Klíčové pojmy, o kterých stránka je, například dveře na míru, masiv, Brno.'),
                Repeater::make('aeo_faq')
                    ->hiddenLabel()
                    ->helperText('Odpovědi na dotazy, které zákazníci opravdu pokládají. Z otázek a odpovědí se sestaví strukturovaná data FAQPage, která se zobrazují ve vyhledávání i v odpovědích AI.')
                    ->schema([
                        TextInput::make('question')
                            ->label('Otázka')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('answer')
                            ->label('Odpověď')
                            ->helperText('Pište celou odpověď včetně konkrétních údajů, ať ji jde citovat samostatně.')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->columns(1)
                    ->addActionLabel('Přidat dotaz')
                    ->reorderable()
                    ->columnSpanFull(),
            ],

            'Technické' => [
                Select::make('key')
                    ->label('Stránka (routa)')
                    ->helperText('Klíč globálního záznamu je zamčený, protože nastavení celého webu drží právě on.')
                    ->options(SeoPage::pageKeyOptions())
                    ->searchable()
                    ->required()
                    ->dehydrated()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?SeoPage $record): bool => (bool) $record?->is_global),
                TextInput::make('label')
                    ->label('Interní název')
                    ->helperText('Zobrazuje se jen v administraci. Do pole page_name se kopíruje automaticky.')
                    ->required()
                    ->maxLength(120),
                TextInput::make('route_name')
                    ->label('Routa v datech (route_name)')
                    ->helperText('Kopie klíče stránky, kterou vyžaduje SEO zadání. Needituje se.')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('page_name')
                    ->label('Název v datech (page_name)')
                    ->helperText('Kopie interního názvu, kterou vyžaduje SEO zadání. Needituje se.')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('path')
                    ->label('Cesta na webu')
                    ->helperText('Například /dvere. Prázdné pole znamená, že se cesta odvodí z routy stránky.')
                    ->maxLength(2048),
                Select::make('robots')
                    ->label('Pokyny pro roboty')
                    ->options(SeoPage::robotsOptions())
                    ->required()
                    ->default(SeoPage::DEFAULT_ROBOTS),
                Toggle::make('in_sitemap')
                    ->label('Zahrnout do sitemap.xml')
                    ->helperText('Vypněte u stránek, které nemají být ve vyhledávačích vůbec vidět.')
                    ->default(true),
                Toggle::make('is_global')
                    ->label('Globální záznam')
                    ->helperText('Globální záznam drží výchozí obrázek, sociální profily, lokalitu a strukturovaná data celého webu. Jeho klíč je zamčený a druhý globální záznam vytvořit nelze.')
                    ->disabled()
                    ->dehydrated(false),
            ],

            'Poznámky' => [
                Textarea::make('notes')
                    ->label('Poznámky')
                    ->helperText('Interní poznámky k metadatům. Nikam se nepublikují, na web se neposílají.')
                    ->rows(5)
                    ->columnSpanFull(),
            ],
        ];
    }

    /**
     * The upload and the external URL write into the one `og_image` column, so a record always has a
     * single sharing picture. The virtual URL field never reaches the model.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeExternalImageUrl(array $data): array
    {
        $externalUrl = trim((string) ($data['og_image_external_url'] ?? ''));

        unset($data['og_image_external_url']);

        if ($externalUrl !== '') {
            $data['og_image'] = $externalUrl;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (self::formSections() as $heading => $components) {
            $sections[] = Section::make($heading)
                ->schema($components)
                ->columns(1)
                ->columnSpanFull();
        }

        return $schema->components($sections);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('label')
                    ->label('Stránka')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label('Routa')
                    ->badge()
                    ->color(fn (SeoPage $record): string => $record->is_global ? 'warning' : 'gray')
                    ->searchable(),
                TextColumn::make('path')
                    ->label('Cesta')
                    ->placeholder('odvozeno z routy')
                    ->toggleable(),
                TextColumn::make('seo_title')
                    ->label('Titulek')
                    ->limit(60)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('robots')
                    ->label('Roboti')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SeoPage::robotsOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => str_contains($state, 'noindex') ? 'warning' : 'success')
                    ->sortable(),
                IconColumn::make('in_sitemap')
                    ->label('V sitemapě')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('robots')
                    ->label('Pokyny pro roboty')
                    ->options(SeoPage::robotsOptions()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            // No delete or bulk delete action on purpose: metadata of a page must stay available.
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoPages::route('/'),
            'create' => CreateSeoPage::route('/create'),
            'edit' => EditSeoPage::route('/{record}/edit'),
        ];
    }
}
