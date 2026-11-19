<?php

namespace App\Models;

use App\Models\Builders\SeoPageBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Metadata of one public page: the values that make up the document head (title, description,
 * canonical, Open Graph, robots) and the structured data used by search engines and AI answers.
 *
 * The table carries both naming schemes of the record. The first columns keep the historical short
 * names (`key`, `label`, `path`, `seo_title`, `seo_description`, `seo_keywords`, `og_title`,
 * `og_description`, `og_type`, `og_image`, `social_profiles`, `locality`, `area_served`,
 * `availability`, `schema_json`, `aeo_faq`, `robots`, `in_sitemap`, `notes`, `is_global`), the
 * columns added by 2026_12_03_000000_add_explicit_seo_fields_to_seo_pages follow the field names of
 * the SEO brief one to one (`route_name`, `page_name`, `canonical_url`, `twitter_title`,
 * `twitter_description`, `social_facebook_url`, `social_instagram_url`, `social_linkedin_url`,
 * `social_youtube_url`, `schema_type`, `aeo_summary`, `aeo_search_intent`, `aeo_entities`,
 * `business_name`, `street_address`, `address_locality`, `postal_code`, `address_country`,
 * `available_languages`, `latitude`, `longitude`, `offers_online`).
 *
 * Two values exist only as mirrors, because the brief and the panel must never disagree about them:
 *
 * - `twitter_image` copies `og_image`, so both networks always share one picture.
 * - `route_name` copies `key` and `page_name` copies `label`. The key stays the unique, locked page
 *   identifier and the label stays the internal name shown in the administration.
 *
 * `locality` and `address_locality` (and `social_profiles` versus the four `social_*_url` columns)
 * describe the same thing in both schemes. The explicit columns win when they are filled, the short
 * columns stay readable as the fallback of a record written before they existed.
 *
 * Rows are seeded from `config/seo.php` (one row per route name plus one `global` row) and then
 * edited in the administration. The public pages never depend on a row existing - SeoPageResolver
 * falls back to the configuration whenever a row is missing.
 *
 * Deletion is guarded on the model level: a record is never deleted permanently, and the global
 * record can not be deleted at all. Bulk deletes are refused by App\Models\Builders\SeoPageBuilder,
 * because Eloquent dispatches no model events for them.
 */
class SeoPage extends Model
{
    use SoftDeletes;

    public const GLOBAL_KEY = 'global';

    public const OG_IMAGE_DISK = 'public';

    public const OG_IMAGE_DIRECTORY = 'seo';

    public const DEFAULT_ROBOTS = 'index,follow';

    /**
     * The `seo_keywords` column is a varchar(512), so merged suggestions must never exceed it.
     */
    public const MAX_KEYWORDS_LENGTH = 512;

    /**
     * The explicit profile columns of the brief, in the order they are published.
     *
     * @var array<string, string>
     */
    public const SOCIAL_COLUMNS = [
        'Facebook' => 'social_facebook_url',
        'Instagram' => 'social_instagram_url',
        'LinkedIn' => 'social_linkedin_url',
        'YouTube' => 'social_youtube_url',
    ];

    /**
     * `twitter_image`, `route_name` and `page_name` are deliberately missing: all three are derived
     * on every save, so a value set from the panel, a seeder or a test can never disagree with the
     * column they mirror.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'label',
        'is_global',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'robots',
        'path',
        'canonical_url',
        'og_title',
        'og_description',
        'og_type',
        'og_image',
        'twitter_title',
        'twitter_description',
        'social_profiles',
        'social_facebook_url',
        'social_instagram_url',
        'social_linkedin_url',
        'social_youtube_url',
        'schema_type',
        'schema_json',
        'aeo_summary',
        'aeo_search_intent',
        'aeo_entities',
        'aeo_faq',
        'business_name',
        'street_address',
        'address_locality',
        'postal_code',
        'address_country',
        'locality',
        'area_served',
        'availability',
        'available_languages',
        'latitude',
        'longitude',
        'offers_online',
        'in_sitemap',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'robots' => self::DEFAULT_ROBOTS,
        'og_type' => 'website',
        'is_global' => false,
        'in_sitemap' => true,
        'offers_online' => false,
    ];

    protected static function booted(): void
    {
        // A record is never deleted permanently: SEO metadata must not be destroyed, no matter
        // whether the call comes from the panel, a command or a controller. The global record is
        // protected from a soft delete as well, because every public page inherits from it.
        static::deleting(function (self $seoPage): bool {
            if ($seoPage->isForceDeleting()) {
                return false;
            }

            return ! $seoPage->is_global;
        });

        static::saving(function (self $seoPage): void {
            $seoPage->key = trim((string) $seoPage->key);
            $seoPage->label = trim((string) $seoPage->label);
            $seoPage->path = self::normalizePath($seoPage->path);
            $seoPage->canonical_url = self::normalizeCanonicalUrl($seoPage->canonical_url);
            $seoPage->og_image = self::normalizeImage($seoPage->og_image);
            $seoPage->social_profiles = self::normalizeSocialProfiles($seoPage->social_profiles) ?: null;

            // Twitter/X never gets its own picture: the column mirrors the Open Graph image and is
            // not part of the form, so an old value can never survive a change of og_image.
            $seoPage->twitter_image = $seoPage->og_image;

            $seoPage->twitter_title = self::normalizeText($seoPage->twitter_title, 255);
            $seoPage->twitter_description = self::normalizeText($seoPage->twitter_description, 512);

            foreach (self::SOCIAL_COLUMNS as $column) {
                $seoPage->{$column} = self::normalizeUrl($seoPage->{$column});
            }

            $seoPage->schema_type = self::normalizeText($seoPage->schema_type, 64);
            $seoPage->aeo_summary = self::normalizeText($seoPage->aeo_summary);
            $seoPage->aeo_search_intent = self::normalizeText($seoPage->aeo_search_intent, 255);
            $seoPage->aeo_entities = self::normalizeStringList($seoPage->aeo_entities) ?: null;

            $seoPage->business_name = self::normalizeText($seoPage->business_name, 255);
            $seoPage->street_address = self::normalizeText($seoPage->street_address, 255);
            $seoPage->address_locality = self::normalizeText($seoPage->address_locality, 120);
            $seoPage->postal_code = self::normalizeText($seoPage->postal_code, 16);
            $seoPage->address_country = self::normalizeText($seoPage->address_country, 64);
            $seoPage->available_languages = self::normalizeStringList($seoPage->available_languages) ?: null;
            $seoPage->latitude = self::normalizeCoordinate($seoPage->latitude, 90.0);
            $seoPage->longitude = self::normalizeCoordinate($seoPage->longitude, 180.0);
            $seoPage->offers_online = (bool) $seoPage->offers_online;

            // The global record is identified by its key alone, so `is_global` can never disagree
            // with the key and a second global record can never be created.
            $seoPage->is_global = (bool) $seoPage->is_global || $seoPage->key === self::GLOBAL_KEY;

            if ($seoPage->is_global) {
                $seoPage->key = self::GLOBAL_KEY;

                $duplicateExists = self::query()
                    ->where('key', self::GLOBAL_KEY)
                    ->when($seoPage->exists, fn ($query) => $query->whereKeyNot($seoPage->getKey()))
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'key' => 'Globální záznam už existuje. Upravte tento záznam, druhý zakládat nelze.',
                    ]);
                }
            }

            // The brief names the two identifiers `route_name` and `page_name`. They mirror the
            // unique key and the internal label, so both naming schemes always describe one page.
            $seoPage->route_name = $seoPage->key;
            $seoPage->page_name = (string) $seoPage->label;
        });
    }

    /**
     * Bulk deletes bypass model events, so the builder is replaced by one that refuses them.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): SeoPageBuilder
    {
        return new SeoPageBuilder($query);
    }

    /**
     * Robots directives offered in the administration.
     *
     * @return array<string, string>
     */
    public static function robotsOptions(): array
    {
        return [
            'index,follow' => 'Indexovat a sledovat odkazy',
            'index,nofollow' => 'Indexovat, nesledovat odkazy',
            'noindex,follow' => 'Neindexovat, sledovat odkazy',
            'noindex,nofollow' => 'Neindexovat ani nesledovat odkazy',
        ];
    }

    /**
     * Open Graph types the frontend actually uses.
     *
     * @return array<string, string>
     */
    public static function ogTypeOptions(): array
    {
        return [
            'website' => 'Web (website)',
            'article' => 'Článek (article)',
            'product' => 'Produkt (product)',
        ];
    }

    /**
     * Vocabulary of the historical `social_profiles` entries. The four networks with an explicit
     * column keep their label here, so a record written before those columns existed is still read
     * with the same platform names. The labels come from the frontend icons.
     *
     * @return array<string, string>
     */
    public static function socialPlatformOptions(): array
    {
        return [
            'Facebook' => 'Facebook',
            'Instagram' => 'Instagram',
            'LinkedIn' => 'LinkedIn',
            'YouTube' => 'YouTube',
            'Jiné' => 'Jiné',
        ];
    }

    /**
     * Route names of the configured pages plus the global record, used as the key selection in the
     * administration.
     *
     * @return array<string, string>
     */
    public static function pageKeyOptions(): array
    {
        $options = [];

        foreach ((array) config('seo.pages', []) as $key => $definition) {
            $pageKey = (string) $key;
            $label = is_array($definition) ? (string) ($definition['label'] ?? $pageKey) : $pageKey;

            $options[$pageKey] = $pageKey === self::GLOBAL_KEY
                ? $label.' (globální, zamčený klíč)'
                : $label.' ('.$pageKey.')';
        }

        return $options;
    }

    /**
     * Attribute values a seeded row is created with. The seeder and the resolver share the same
     * configuration, so a missing row and a seeded row always describe the page identically.
     *
     * The short keys of `config/seo.php` (`title`, `description`, `keywords`, `structured_data`,
     * `faqs`) are the definition of a page, not a copy of the column names. The brief fields use
     * their exact column names, so a definition may fill `schema_type`, `aeo_summary`,
     * `business_name`, `street_address`, `address_locality`, `postal_code`, `address_country`,
     * `available_languages`, `latitude`, `longitude`, `offers_online`, `canonical_url`,
     * `twitter_title`, `twitter_description` and the four `social_*_url` columns directly.
     *
     * `route_name` and `page_name` are not listed: the saving event mirrors them from the key and
     * the label of the record.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function attributesFromDefinition(array $definition): array
    {
        // `locality` and `address_locality` are the same place under two names, so either key of a
        // definition fills both columns of the record.
        $addressLocality = self::normalizeText($definition['address_locality'] ?? $definition['locality'] ?? null, 120);
        $legacyLocality = self::normalizeText($definition['locality'] ?? $definition['address_locality'] ?? null, 120);

        return [
            'label' => (string) ($definition['label'] ?? ''),
            'seo_title' => (string) ($definition['title'] ?? ''),
            'seo_description' => $definition['description'] ?? null,
            'seo_keywords' => $definition['keywords'] ?? null,
            'robots' => (string) ($definition['robots'] ?? self::DEFAULT_ROBOTS),
            'og_type' => (string) ($definition['og_type'] ?? 'website'),
            'path' => self::normalizePath($definition['path'] ?? null),
            'canonical_url' => self::normalizeCanonicalUrl($definition['canonical_url'] ?? null),
            'og_image' => self::normalizeImage($definition['og_image'] ?? null),
            'twitter_title' => self::normalizeText($definition['twitter_title'] ?? null, 255),
            'twitter_description' => self::normalizeText($definition['twitter_description'] ?? null, 512),
            'social_profiles' => self::normalizeSocialProfiles($definition['social_profiles'] ?? null) ?: null,
            'social_facebook_url' => self::normalizeUrl($definition['social_facebook_url'] ?? null),
            'social_instagram_url' => self::normalizeUrl($definition['social_instagram_url'] ?? null),
            'social_linkedin_url' => self::normalizeUrl($definition['social_linkedin_url'] ?? null),
            'social_youtube_url' => self::normalizeUrl($definition['social_youtube_url'] ?? null),
            'schema_type' => self::normalizeText($definition['schema_type'] ?? null, 64),
            'schema_json' => $definition['structured_data'] ?? null,
            'aeo_summary' => self::normalizeText($definition['aeo_summary'] ?? null),
            'aeo_search_intent' => self::normalizeText($definition['aeo_search_intent'] ?? null, 255),
            'aeo_entities' => self::normalizeStringList($definition['aeo_entities'] ?? null) ?: null,
            'aeo_faq' => self::normalizeFaqs($definition['faqs'] ?? null) ?: null,
            'business_name' => self::normalizeText($definition['business_name'] ?? null, 255),
            'street_address' => self::normalizeText($definition['street_address'] ?? null, 255),
            'address_locality' => $addressLocality,
            'postal_code' => self::normalizeText($definition['postal_code'] ?? null, 16),
            'address_country' => self::normalizeText($definition['address_country'] ?? null, 64),
            'locality' => $legacyLocality,
            'area_served' => self::normalizeText($definition['area_served'] ?? null, 255),
            'availability' => self::normalizeText($definition['availability'] ?? null, 255),
            'available_languages' => self::normalizeStringList($definition['available_languages'] ?? null) ?: null,
            'latitude' => self::normalizeCoordinate($definition['latitude'] ?? null, 90.0),
            'longitude' => self::normalizeCoordinate($definition['longitude'] ?? null, 180.0),
            'offers_online' => (bool) ($definition['offers_online'] ?? false),
            'in_sitemap' => (bool) ($definition['in_sitemap'] ?? true),
        ];
    }

    /**
     * Path of the page used for the canonical URL and for the sitemap: the stored path when the
     * editors filled it in, otherwise the path of the route the record belongs to.
     */
    public function resolvedPath(): string
    {
        $path = self::normalizePath($this->path);

        if ($path !== null) {
            return $path;
        }

        $pageKey = trim((string) $this->key);

        if ($pageKey !== '' && Route::has($pageKey)) {
            $routePath = route($pageKey, absolute: false);

            return self::normalizePath(is_string($routePath) ? $routePath : null) ?? '/';
        }

        return '/';
    }

    /**
     * The single absolute URL of the sharing image stored in `og_image`.
     */
    public function ogImageUrl(): ?string
    {
        return self::resolveImageUrl($this->og_image);
    }

    /**
     * The Twitter/X image. It always mirrors `og_image`; the fallback only covers a record written
     * before the column existed, so both networks never disagree.
     */
    public function twitterImageUrl(): ?string
    {
        return self::resolveImageUrl($this->twitter_image) ?? $this->ogImageUrl();
    }

    /**
     * FAQs in the shape the FAQPage structured data consumes.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqItems(): array
    {
        return self::normalizeFaqs($this->aeo_faq);
    }

    /**
     * Profiles in the shape the structured data consumes as `sameAs`: the explicit columns of the
     * brief first, then the historical repeater entries that are not already covered by them.
     *
     * @return list<array{platform: string, url: string}>
     */
    public function socialProfiles(): array
    {
        $profiles = [];

        foreach (self::SOCIAL_COLUMNS as $platform => $column) {
            $url = self::normalizeUrl($this->{$column});

            if ($url !== null) {
                $profiles[] = ['platform' => $platform, 'url' => $url];
            }
        }

        $known = array_column($profiles, 'url');

        foreach (self::normalizeSocialProfiles($this->social_profiles) as $profile) {
            if (in_array($profile['url'], $known, true)) {
                continue;
            }

            $profiles[] = $profile;
            $known[] = $profile['url'];
        }

        return $profiles;
    }

    /**
     * Canonical of the record when an editor filled it in, otherwise null so the caller keeps
     * deriving the canonical from the path and the route. A stored path is turned into an absolute
     * URL with the configured application URL, so a canonical written by hand is always absolute.
     */
    public function resolvedCanonicalUrl(): ?string
    {
        $canonical = self::normalizeCanonicalUrl($this->canonical_url);

        if ($canonical === null) {
            return null;
        }

        return self::normalizeUrl($canonical) ?? rtrim((string) config('app.url'), '/').$canonical;
    }

    /**
     * Turns a stored image value into one absolute URL: an external URL is validated and returned
     * as it is, a path is converted to its public disk URL.
     */
    public static function resolveImageUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return self::normalizeUrl($value) ?? Storage::disk(self::OG_IMAGE_DISK)->url($value);
    }

    /**
     * Normalises a path typed by an editor: a pasted absolute URL is reduced to its path, duplicate
     * slashes are collapsed and a trailing slash is dropped, so `/dvere/` and
     * `https://example.test/dvere` both become `/dvere`.
     */
    public static function normalizePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '://')) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = is_string($path) ? $path : '/';

            if ($value === '') {
                return '/';
            }
        }

        $value = explode('#', explode('?', $value)[0])[0];
        $value = '/'.ltrim($value, '/');
        $value = preg_replace('#/+#', '/', $value) ?? $value;
        $value = rtrim($value, '/');

        return $value === '' ? '/' : $value;
    }

    /**
     * Accepts only absolute http(s) URLs, because everything else would produce a broken canonical,
     * a broken `og:image` or a broken `sameAs` entry.
     */
    public static function normalizeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true) ? $value : null;
    }

    /**
     * The `og_image` column holds either an absolute http(s) URL or a path on the public disk. Every
     * other value (a `javascript:` or `data:` URI, whitespace) is dropped instead of being stored.
     */
    public static function normalizeImage(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $url = self::normalizeUrl($value);

        if ($url !== null) {
            return $url;
        }

        // Anything carrying a URI scheme is not a file of this disk.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value) === 1) {
            return null;
        }

        return ltrim($value, '/');
    }

    /**
     * @return list<array{platform: string, url: string}>
     */
    public static function normalizeSocialProfiles(mixed $profiles): array
    {
        if (! is_array($profiles)) {
            return [];
        }

        $normalized = [];

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $platform = trim((string) ($profile['platform'] ?? ''));
            $url = self::normalizeUrl($profile['url'] ?? null);

            if ($platform === '' || $url === null) {
                continue;
            }

            $normalized[] = ['platform' => $platform, 'url' => $url];
        }

        return $normalized;
    }

    /**
     * The `canonical_url` column accepts an absolute http(s) address or a path of this site. Any
     * other scheme (`javascript:`, `data:`), a fragment and a query string are dropped, and both
     * forms are reduced to one spelling (no trailing slash, one slash after the host), so the same
     * page can never publish two different canonicals.
     */
    public static function normalizeCanonicalUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true)) {
            $host = parse_url($value, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                return null;
            }

            $port = parse_url($value, PHP_URL_PORT);
            $authority = strtolower($scheme).'://'.$host.(is_int($port) ? ':'.$port : '');

            return $authority.self::normalizePath($value);
        }

        // Anything carrying another URI scheme is not a path of this site.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value) === 1) {
            return null;
        }

        return self::normalizePath($value);
    }

    /**
     * Trims a free text field and optionally cuts it to the length of its column, so a pasted long
     * value can never fail the write on a database that enforces the limit.
     */
    public static function normalizeText(mixed $value, ?int $maxLength = null): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $maxLength === null ? $value : mb_substr($value, 0, $maxLength);
    }

    /**
     * A comma separated string or a list of values, cleaned of empty entries and duplicates.
     *
     * @return list<string>
     */
    public static function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $string = self::normalizeText($item);

            if ($string === null || in_array($string, $items, true)) {
                continue;
            }

            $items[] = $string;
        }

        return $items;
    }

    /**
     * Coordinates are written with a decimal comma in Czech, so both separators are accepted. A
     * value outside the range of the axis is dropped instead of being published in JSON-LD.
     */
    public static function normalizeCoordinate(mixed $value, float $limit): ?float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return abs($number) > $limit ? null : $number;
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function normalizeFaqs(mixed $faqs): array
    {
        if (! is_array($faqs)) {
            return [];
        }

        $items = [];

        foreach ($faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = ['question' => $question, 'answer' => $answer];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'in_sitemap' => 'boolean',
            'offers_online' => 'boolean',
            'aeo_faq' => 'array',
            'aeo_entities' => 'array',
            'available_languages' => 'array',
            'social_profiles' => 'array',
            'schema_json' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
