<?php

namespace App\Services\Seo;

use App\Models\SeoPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds the single SEO payload that both renderers consume: the Blade root view renders it into
 * the first response, the Vue `SeoHead` component re-renders the same array after client-side
 * navigation. Nothing is rendered twice, because the payload is the only source of truth.
 *
 * The payload is resolved from `seo_pages` when a row exists and falls back to `config/seo.php`
 * otherwise, so the head stays complete even before the table is migrated or seeded. The single
 * global record supplies the site wide defaults (sharing image, social profiles, locality and the
 * organization node of the structured data).
 */
class SeoPageResolver
{
    /**
     * Structured data types that describe the company itself, so the site URL may be filled in
     * when the editor did not provide one.
     */
    private const SITE_NODE_TYPES = [
        'Organization',
        'LocalBusiness',
        'ProfessionalService',
        'HomeAndConstructionBusiness',
        'Carpenter',
        'Store',
        'WebSite',
    ];

    private const JSON_LD_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;

    private ?bool $seoPagesTableExists = null;

    /**
     * All records of the request, keyed by page key. Loaded once, so a sitemap with many pages
     * never turns into one query per page.
     *
     * @var array<string, SeoPage>|null
     */
    private ?array $pagesByKey = null;

    /**
     * Shape consumed by resources/views/partials/seo.blade.php and by the Vue SeoHead component.
     *
     * Every field of the SEO brief is present under its own name, so a consumer never has to know
     * that `locality` and `address_locality` (or `canonical` and `canonical_url`) describe the same
     * value. The short keys stay in the payload, because the already rendered tags and the Vue
     * component read them.
     *
     * @return array{
     *     key: string|null,
     *     route_name: string|null,
     *     page_name: string|null,
     *     is_global: bool,
     *     path: string,
     *     title: string,
     *     description: string|null,
     *     keywords: string|null,
     *     canonical: string,
     *     canonical_url: string,
     *     robots: string,
     *     og_title: string,
     *     og_description: string|null,
     *     og_type: string,
     *     og_site_name: string,
     *     og_locale: string,
     *     og_url: string,
     *     og_image: string|null,
     *     twitter_card: string,
     *     twitter_title: string,
     *     twitter_description: string|null,
     *     twitter_image: string|null,
     *     json_ld: string|null,
     *     faqs: list<array{question: string, answer: string}>,
     *     schema_type: string|null,
     *     aeo_summary: string|null,
     *     aeo_search_intent: string|null,
     *     aeo_entities: list<string>,
     *     business_name: string|null,
     *     street_address: string|null,
     *     address_locality: string|null,
     *     postal_code: string|null,
     *     address_country: string|null,
     *     available_languages: list<string>,
     *     latitude: float|null,
     *     longitude: float|null,
     *     offers_online: bool,
     *     social_profiles: list<array{platform: string, url: string}>,
     *     social_facebook_url: string|null,
     *     social_instagram_url: string|null,
     *     social_linkedin_url: string|null,
     *     social_youtube_url: string|null,
     *     locality: string|null,
     *     area_served: string|null,
     *     availability: string|null
     * }
     */
    public function payloadFor(Request $request): array
    {
        $pageKey = $request->route()?->getName();
        $page = $this->pageForKey($pageKey);
        $global = $this->globalPage();
        $definition = $this->definition($pageKey);

        $pageTitle = $this->firstFilled([$page?->seo_title, $definition['title'] ?? null]);
        $title = $this->fullTitle($pageTitle);
        $description = $this->firstFilled([$page?->seo_description, $definition['description'] ?? null]);
        $path = $this->pagePath($page, $definition, $request);

        // An editor can pin the canonical in `canonical_url`. Without that value the canonical keeps
        // following the path of the page, so a record written before the column existed is unchanged.
        $canonical = $page?->resolvedCanonicalUrl() ?? $this->absoluteUrl($path);

        // The title tag carries the site name, the Open Graph title stays the clean page title. The
        // Twitter/X text of the brief falls back to the Open Graph text, so both networks agree
        // unless an editor deliberately wrote a different one.
        $ogTitle = $this->firstFilled([$page?->og_title]) ?? $pageTitle ?? $title;
        $ogDescription = $this->firstFilled([$page?->og_description]) ?? $description;
        $twitterTitle = $this->firstFilled([$page?->twitter_title]) ?? $ogTitle;
        $twitterDescription = $this->firstFilled([$page?->twitter_description]) ?? $ogDescription;

        // `locality` is the historical name of the place the brief calls `address_locality`.
        $locality = $this->localityText($page, $global, $definition);

        return [
            'key' => $pageKey,
            'route_name' => $this->firstFilled([$page?->route_name]) ?? $pageKey,
            'page_name' => $this->firstFilled([$page?->page_name, $page?->label, $definition['label'] ?? null]),
            'is_global' => (bool) ($page?->is_global ?? false),
            'path' => $path,
            'title' => $title,
            'description' => $description,
            'keywords' => $this->firstFilled([$page?->seo_keywords, $definition['keywords'] ?? null]),
            'canonical' => $canonical,
            'canonical_url' => $canonical,
            'robots' => $this->firstFilled([$page?->robots, $definition['robots'] ?? null])
                ?? (string) config('seo.default_robots'),
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_type' => $this->firstFilled([$page?->og_type, $definition['og_type'] ?? null])
                ?? (string) config('seo.og_type'),
            'og_site_name' => (string) config('seo.og_site_name'),
            'og_locale' => (string) config('seo.og_locale'),
            'og_url' => $canonical,
            'og_image' => $this->ogImageUrl($page, $global),
            // The Twitter/X image is never composed separately: it is the Open Graph image, so both
            // networks always show the same picture.
            'twitter_card' => (string) config('seo.twitter_card'),
            'twitter_title' => $twitterTitle,
            'twitter_description' => $twitterDescription,
            'twitter_image' => $this->twitterImageUrl($page, $global),
            'json_ld' => $this->jsonLd($page, $definition),
            'faqs' => $page?->faqItems() ?? [],
            'schema_type' => $this->columnValue('schema_type', $page, null, $definition),
            'aeo_summary' => $this->columnValue('aeo_summary', $page, $global, $definition),
            'aeo_search_intent' => $this->columnValue('aeo_search_intent', $page, $global, $definition),
            'aeo_entities' => $this->columnList('aeo_entities', $page, $global, $definition),
            'business_name' => $this->columnValue('business_name', $page, $global, $definition),
            'street_address' => $this->columnValue('street_address', $page, $global, $definition),
            'address_locality' => $locality,
            'postal_code' => $this->columnValue('postal_code', $page, $global, $definition),
            'address_country' => $this->columnValue('address_country', $page, $global, $definition),
            'available_languages' => $this->columnList('available_languages', $page, $global, $definition),
            'latitude' => $this->columnNumber('latitude', $page, $global, $definition),
            'longitude' => $this->columnNumber('longitude', $page, $global, $definition),
            'offers_online' => $this->offersOnline($page, $global, $definition),
            'social_profiles' => $this->socialProfiles($page, $global),
            'social_facebook_url' => $this->socialUrl('social_facebook_url', $page, $global, $definition),
            'social_instagram_url' => $this->socialUrl('social_instagram_url', $page, $global, $definition),
            'social_linkedin_url' => $this->socialUrl('social_linkedin_url', $page, $global, $definition),
            'social_youtube_url' => $this->socialUrl('social_youtube_url', $page, $global, $definition),
            'locality' => $locality,
            'area_served' => $this->sharedText('area_served', $page, $global),
            'availability' => $this->sharedText('availability', $page, $global),
        ];
    }

    /**
     * URLs of the pages that belong to the sitemap: configured pages the editors did not exclude
     * and that are not marked as `noindex`.
     *
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function sitemapEntries(): array
    {
        $entries = [];

        foreach ($this->configuredPages() as $pageKey => $definition) {
            $definition = is_array($definition) ? $definition : [];

            if (($definition['in_sitemap'] ?? true) !== true) {
                continue;
            }

            $routeName = (string) $pageKey;

            // The global record and any other key without a route never describes a page.
            if ($routeName === SeoPage::GLOBAL_KEY || ! Route::has($routeName)) {
                continue;
            }

            $page = $this->pageForKey($routeName);

            if ($page !== null && ! $page->in_sitemap) {
                continue;
            }

            $robots = $this->firstFilled([$page?->robots, $definition['robots'] ?? null])
                ?? (string) config('seo.default_robots');

            if (str_contains($robots, 'noindex')) {
                continue;
            }

            $entries[] = [
                'loc' => $this->absoluteUrl($this->pathFor($page, $definition, $routeName)),
                'lastmod' => $page?->updated_at?->toAtomString(),
            ];
        }

        return $entries;
    }

    public function sitemapUrl(): string
    {
        return $this->absoluteUrl('/sitemap.xml');
    }

    /**
     * Absolute URL built from the configured application URL, so a canonical never points to a
     * preview host or to the host of the current request.
     */
    public function absoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Path of the current page: the stored path, then the configured path, then the path of the
     * request that is being answered.
     *
     * @param  array<string, mixed>  $definition
     */
    private function pagePath(?SeoPage $page, array $definition, Request $request): string
    {
        $path = SeoPage::normalizePath($page?->path) ?? SeoPage::normalizePath($definition['path'] ?? null);

        if ($path !== null) {
            return $path;
        }

        $requestPath = $request->getPathInfo();

        return $requestPath === '' ? '/' : $requestPath;
    }

    /**
     * Path of a page listed in the sitemap: the pinned canonical, the stored path, the configured
     * path, then the route path. The canonical wins, because a page must never be listed under an
     * address that its own `<link rel="canonical">` denies.
     *
     * @param  array<string, mixed>  $definition
     */
    private function pathFor(?SeoPage $page, array $definition, string $routeName): string
    {
        $path = $this->canonicalPath($page)
            ?? SeoPage::normalizePath($page?->path)
            ?? SeoPage::normalizePath($definition['path'] ?? null);

        if ($path !== null) {
            return $path;
        }

        if ($page !== null) {
            return $page->resolvedPath();
        }

        $routePath = route($routeName, absolute: false);

        return SeoPage::normalizePath(is_string($routePath) ? $routePath : null) ?? '/';
    }

    /**
     * Path of a canonical that points at this site, otherwise null. A canonical may deliberately
     * name another domain (consolidating a duplicated campaign site), and such a page must keep its
     * own address in the sitemap.
     */
    private function canonicalPath(?SeoPage $page): ?string
    {
        $canonical = $page?->resolvedCanonicalUrl();

        if ($canonical === null) {
            return null;
        }

        $host = parse_url($canonical, PHP_URL_HOST);
        $siteHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || ! is_string($siteHost) || strcasecmp($host, $siteHost) !== 0) {
            return null;
        }

        $path = parse_url($canonical, PHP_URL_PATH);

        return SeoPage::normalizePath(is_string($path) ? $path : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(?string $pageKey): array
    {
        if ($pageKey === null || $pageKey === '') {
            return [];
        }

        $pages = $this->configuredPages();

        // Route names contain dots, so they are read as array keys instead of config dot notation.
        return isset($pages[$pageKey]) && is_array($pages[$pageKey]) ? $pages[$pageKey] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredPages(): array
    {
        $pages = config('seo.pages', []);

        return is_array($pages) ? $pages : [];
    }

    private function pageForKey(?string $pageKey): ?SeoPage
    {
        if ($pageKey === null || $pageKey === '') {
            return null;
        }

        return $this->pagesByKey()[$pageKey] ?? null;
    }

    private function globalPage(): ?SeoPage
    {
        return $this->pageForKey(SeoPage::GLOBAL_KEY);
    }

    /**
     * Every public page keeps working before the migration has been run, which is why the table
     * lookup is guarded and resolved only once per resolver instance.
     *
     * @return array<string, SeoPage>
     */
    private function pagesByKey(): array
    {
        if ($this->pagesByKey !== null) {
            return $this->pagesByKey;
        }

        if (! $this->hasSeoPagesTable()) {
            return $this->pagesByKey = [];
        }

        /** @var array<string, SeoPage> $pages */
        $pages = SeoPage::query()->get()->keyBy('key')->all();

        return $this->pagesByKey = $pages;
    }

    private function hasSeoPagesTable(): bool
    {
        return $this->seoPagesTableExists ??= Schema::hasTable('seo_pages');
    }

    private function fullTitle(?string $title): string
    {
        $title = trim((string) $title);
        $siteName = (string) config('seo.site_name');

        if ($title === '') {
            return $siteName;
        }

        return Str::contains($title, $siteName) ? $title : $title.(string) config('seo.title_suffix');
    }

    /**
     * One absolute URL of the sharing image: the page image wins, the global record is the fallback
     * and `seo.default_og_image_url` is the last resort.
     */
    private function ogImageUrl(?SeoPage $page, ?SeoPage $global): ?string
    {
        return $page?->ogImageUrl()
            ?? $global?->ogImageUrl()
            ?? $this->firstFilled([config('seo.default_og_image_url')]);
    }

    /**
     * The Twitter/X image of the page, then of the global record, then the Open Graph image, which is
     * the same picture by definition.
     */
    private function twitterImageUrl(?SeoPage $page, ?SeoPage $global): ?string
    {
        return $page?->twitterImageUrl()
            ?? $global?->twitterImageUrl()
            ?? $this->ogImageUrl($page, $global);
    }

    /**
     * @return list<array{platform: string, url: string}>
     */
    private function socialProfiles(?SeoPage $page, ?SeoPage $global): array
    {
        $profiles = $page?->socialProfiles() ?? [];

        if ($profiles !== []) {
            return $profiles;
        }

        return $global?->socialProfiles() ?? [];
    }

    /**
     * Value of a locality style field: page record, then global record, then configuration.
     */
    private function sharedText(string $attribute, ?SeoPage $page, ?SeoPage $global): ?string
    {
        return $this->firstFilled([
            $page?->{$attribute},
            $global?->{$attribute},
            config('seo.'.$attribute),
        ]);
    }

    /**
     * Value of a brief field: page record, page definition, global record, then the top level
     * configuration. An edited value of the current page always wins over a site wide default.
     *
     * @param  array<string, mixed>  $definition
     */
    private function columnValue(string $attribute, ?SeoPage $page, ?SeoPage $global, array $definition): ?string
    {
        return $this->firstFilled([
            $page?->{$attribute},
            $definition[$attribute] ?? null,
            $global?->{$attribute},
            config('seo.'.$attribute),
        ]);
    }

    /**
     * The place of the record: `address_locality` of the brief first, then the historical
     * `locality` of the same record, the definition and finally the global record.
     *
     * @param  array<string, mixed>  $definition
     */
    private function localityText(?SeoPage $page, ?SeoPage $global, array $definition): ?string
    {
        return $this->firstFilled([
            $page?->address_locality,
            $page?->locality,
            $definition['address_locality'] ?? null,
            $definition['locality'] ?? null,
            $global?->address_locality,
            $global?->locality,
            config('seo.address_locality'),
            config('seo.locality'),
        ]);
    }

    /**
     * One profile URL of the brief: page record, definition, then the global record.
     *
     * @param  array<string, mixed>  $definition
     */
    private function socialUrl(string $attribute, ?SeoPage $page, ?SeoPage $global, array $definition): ?string
    {
        foreach ([$page?->{$attribute}, $definition[$attribute] ?? null, $global?->{$attribute}] as $candidate) {
            $url = SeoPage::normalizeUrl($candidate);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    private function columnList(string $attribute, ?SeoPage $page, ?SeoPage $global, array $definition): array
    {
        foreach ([
            $page?->{$attribute},
            $definition[$attribute] ?? null,
            $global?->{$attribute},
            config('seo.'.$attribute),
        ] as $candidate) {
            $items = SeoPage::normalizeStringList($candidate);

            if ($items !== []) {
                return $items;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function columnNumber(string $attribute, ?SeoPage $page, ?SeoPage $global, array $definition): ?float
    {
        $limit = $attribute === 'latitude' ? 90.0 : 180.0;

        foreach ([
            $page?->{$attribute},
            $definition[$attribute] ?? null,
            $global?->{$attribute},
            config('seo.'.$attribute),
        ] as $candidate) {
            $number = SeoPage::normalizeCoordinate($candidate, $limit);

            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Ordering online is a property of the workshop, so a page inherits it from the global record
     * and never switches it off on its own.
     *
     * @param  array<string, mixed>  $definition
     */
    private function offersOnline(?SeoPage $page, ?SeoPage $global, array $definition): bool
    {
        if (($page?->offers_online ?? false) || ($global?->offers_online ?? false)) {
            return true;
        }

        if (($definition['offers_online'] ?? null) === true) {
            return true;
        }

        return (bool) config('seo.offers_online', false);
    }

    /**
     * Structured data is emitted as a ready to print JSON string, so the Blade partial and the Vue
     * component cannot diverge and no unescaped `</script>` can ever reach the document.
     *
     * @param  array<string, mixed>  $definition
     */
    private function jsonLd(?SeoPage $page, array $definition): ?string
    {
        $nodes = $this->withGlobalNodes($this->structuredDataNodes($page, $definition));

        $faqNode = $this->faqNode($page);

        if ($faqNode !== null && ! $this->containsNodeType($nodes, 'FAQPage')) {
            $nodes[] = $faqNode;
        }

        if ($nodes === []) {
            return null;
        }

        $socialProfiles = $page?->socialProfiles() ?: ($this->globalPage()?->socialProfiles() ?? []);
        $areaServed = $this->sharedText('area_served', $page, $this->globalPage());

        $nodes = array_map(
            fn (array $node): array => $this->enrichNode($node, $socialProfiles, $areaServed),
            $nodes,
        );

        $payload = count($nodes) === 1 ? $nodes[0] : $nodes;

        $encoded = json_encode($payload, self::JSON_LD_FLAGS);

        return $encoded === false ? null : $encoded;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    private function structuredDataNodes(?SeoPage $page, array $definition): array
    {
        $data = $page?->schema_json;

        if (! is_array($data) || $data === []) {
            $data = $definition['structured_data'] ?? null;
        }

        if (! is_array($data) || $data === []) {
            // No hand written JSON-LD: the explicit columns of the record describe the node instead.
            $node = $this->columnNode($page, $definition);

            return $node === [] ? [] : [$node];
        }

        if (! array_is_list($data)) {
            return [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * Structured data node composed from the explicit columns of the brief: the type, the business
     * name and its postal address, the coordinates, the languages, the entities the page is about
     * and the online ordering flag. It is used only when the record carries no hand written
     * JSON-LD, so an editor never fights a generated node.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function columnNode(?SeoPage $page, array $definition): array
    {
        $global = $this->globalPage();
        // The type describes the page itself, so it never falls back to the type of the site node.
        $type = $this->columnValue('schema_type', $page, null, $definition);

        if ($type === null) {
            return [];
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $this->columnNodeName($type, $page, $global, $definition),
        ];

        $address = array_filter([
            'streetAddress' => $this->columnValue('street_address', $page, $global, $definition),
            'addressLocality' => $this->localityText($page, $global, $definition),
            'postalCode' => $this->columnValue('postal_code', $page, $global, $definition),
            'addressCountry' => $this->columnValue('address_country', $page, $global, $definition),
        ], fn (?string $value): bool => $value !== null);

        if ($address !== []) {
            $node['address'] = ['@type' => 'PostalAddress', ...$address];
        }

        $latitude = $this->columnNumber('latitude', $page, $global, $definition);
        $longitude = $this->columnNumber('longitude', $page, $global, $definition);

        if ($latitude !== null && $longitude !== null) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        $languages = $this->columnList('available_languages', $page, $global, $definition);

        if ($languages !== []) {
            $node['availableLanguage'] = $languages;
        }

        $entities = $this->columnList('aeo_entities', $page, $global, $definition);

        if ($entities !== []) {
            $node['about'] = array_map(
                fn (string $entity): array => ['@type' => 'Thing', 'name' => $entity],
                $entities,
            );
        }

        if ($this->offersOnline($page, $global, $definition)) {
            // "Order online" is published as an order action that starts on this site.
            $node['potentialAction'] = [
                '@type' => 'OrderAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->absoluteUrl('/'),
                ],
            ];
        }

        return $node;
    }

    /**
     * Name of the generated node: a node that describes the company itself is named after the
     * business, a node that describes the page itself after the title of the page.
     *
     * @param  array<string, mixed>  $definition
     */
    private function columnNodeName(string $type, ?SeoPage $page, ?SeoPage $global, array $definition): string
    {
        $businessName = $this->columnValue('business_name', $page, $global, $definition);
        $pageTitle = $this->firstFilled([$page?->seo_title, $definition['title'] ?? null]);

        $name = in_array($type, self::SITE_NODE_TYPES, true)
            ? $businessName ?? $pageTitle
            : $pageTitle ?? $businessName;

        return $name ?? (string) config('seo.site_name');
    }

    /**
     * The site wide node of the global record is added to every page, unless the page already
     * describes the same schema.org type itself.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function withGlobalNodes(array $nodes): array
    {
        $global = $this->globalPage();

        if ($global === null) {
            return $nodes;
        }

        foreach ($this->structuredDataNodes($global, []) as $globalNode) {
            $type = $this->nodeType($globalNode);

            if ($type !== null && $this->containsNodeType($nodes, $type)) {
                continue;
            }

            $nodes[] = $globalNode;
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function nodeTypes(array $node): array
    {
        $types = [];

        foreach ((array) ($node['@type'] ?? []) as $type) {
            if (is_string($type) && $type !== '') {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function nodeType(array $node): ?string
    {
        return $this->nodeTypes($node)[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function containsNodeType(array $nodes, string $type): bool
    {
        foreach ($nodes as $node) {
            foreach ($this->nodeTypes($node) as $nodeType) {
                if (strcasecmp($nodeType, $type) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array{platform: string, url: string}>  $socialProfiles
     * @return array<string, mixed>
     */
    private function enrichNode(array $node, array $socialProfiles, ?string $areaServed): array
    {
        if (! $this->isSiteNode($node)) {
            return $node;
        }

        if (! filled($node['url'] ?? null)) {
            $node['url'] = $this->absoluteUrl('/');
        }

        if (! filled($node['sameAs'] ?? null) && $socialProfiles !== []) {
            $node['sameAs'] = array_values(array_map(
                fn (array $profile): string => $profile['url'],
                $socialProfiles,
            ));
        }

        if (! filled($node['areaServed'] ?? null) && $areaServed !== null) {
            $node['areaServed'] = $areaServed;
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isSiteNode(array $node): bool
    {
        foreach ($this->nodeTypes($node) as $type) {
            if (in_array($type, self::SITE_NODE_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faqNode(?SeoPage $page): ?array
    {
        $items = $page?->faqItems() ?? [];

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ], $items),
        ];
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
