<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the field names of the SEO/AEO brief as first class columns:
 * `route_name`, `page_name`, `canonical_url`, `twitter_title`, `twitter_description`,
 * `social_facebook_url`, `social_instagram_url`, `social_linkedin_url`, `social_youtube_url`,
 * `schema_type`, `aeo_summary`, `aeo_search_intent`, `aeo_entities`, `business_name`,
 * `street_address`, `address_locality`, `postal_code`, `address_country`, `available_languages`,
 * `latitude`, `longitude` and `offers_online`.
 *
 * The earlier short names (`key`, `label`, `path`, `locality`, `social_profiles`, `schema_json`)
 * stay in place. They are the historical storage of the same information, so this migration never
 * renames or drops anything and an existing database keeps every value it had. The brief fields are
 * filled from those columns, which makes both naming schemes readable at the same time.
 *
 * `route_name` and `page_name` are mirrors of the unique `key` and of the `label`; SeoPage keeps
 * them in sync on every save, so they are filled here only for the rows that already exist.
 *
 * `canonical_url` stays empty on purpose: the resolver derives the canonical from the stored `path`
 * (or from the route) and only an explicit editorial override is written into this column.
 */
return new class extends Migration
{
    /**
     * Platform labels used by the social profile repeater, mapped to the explicit columns.
     *
     * @var array<string, string>
     */
    private const SOCIAL_COLUMNS = [
        'facebook' => 'social_facebook_url',
        'instagram' => 'social_instagram_url',
        'linkedin' => 'social_linkedin_url',
        'youtube' => 'social_youtube_url',
    ];

    /**
     * @var array<string, string>
     */
    private const ADDRESS_COLUMNS = [
        'streetAddress' => 'street_address',
        'addressLocality' => 'address_locality',
        'postalCode' => 'postal_code',
        'addressCountry' => 'address_country',
    ];

    public function up(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->string('route_name', 64)->nullable();
            $table->string('page_name', 120)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('twitter_title', 255)->nullable();
            $table->string('twitter_description', 512)->nullable();
            $table->string('social_facebook_url', 512)->nullable();
            $table->string('social_instagram_url', 512)->nullable();
            $table->string('social_linkedin_url', 512)->nullable();
            $table->string('social_youtube_url', 512)->nullable();
            $table->string('schema_type', 64)->nullable();
            $table->text('aeo_summary')->nullable();
            $table->string('aeo_search_intent', 255)->nullable();
            $table->json('aeo_entities')->nullable();
            $table->string('business_name', 255)->nullable();
            $table->string('street_address', 255)->nullable();
            $table->string('address_locality', 120)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('address_country', 64)->nullable();
            $table->json('available_languages')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // A flag, not a nullable value: every record states whether the offer can be ordered
            // online, so a missing value can never be mistaken for a "no" written by an editor.
            $table->boolean('offers_online')->default(false);
        });

        $this->backfillFromShortColumns();
    }

    public function down(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->dropColumn([
                'route_name',
                'page_name',
                'canonical_url',
                'twitter_title',
                'twitter_description',
                'social_facebook_url',
                'social_instagram_url',
                'social_linkedin_url',
                'social_youtube_url',
                'schema_type',
                'aeo_summary',
                'aeo_search_intent',
                'aeo_entities',
                'business_name',
                'street_address',
                'address_locality',
                'postal_code',
                'address_country',
                'available_languages',
                'latitude',
                'longitude',
                'offers_online',
            ]);
        });
    }

    /**
     * Copies the values of the historical columns into the brief fields. Soft deleted rows are
     * included, because a retired page keeps its metadata.
     *
     * The rows are read and written in chunks with the query builder instead of one correlated SQL
     * statement, because the JSON columns have to be read the same way on MySQL, PostgreSQL and
     * SQLite.
     */
    private function backfillFromShortColumns(): void
    {
        DB::table('seo_pages')
            ->select(['id', 'key', 'label', 'locality', 'social_profiles', 'schema_json'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $values = $this->derivedValues($row);

                    if ($values === []) {
                        continue;
                    }

                    DB::table('seo_pages')->where('id', $row->id)->update($values);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function derivedValues(object $row): array
    {
        $values = [];

        $routeName = $this->firstString($row->key ?? null);
        $pageName = $this->firstString($row->label ?? null);

        if ($routeName !== null) {
            $values['route_name'] = $routeName;
        }

        if ($pageName !== null) {
            $values['page_name'] = $pageName;
        }

        foreach ($this->socialUrls($row->social_profiles ?? null) as $column => $url) {
            $values[$column] = $url;
        }

        $node = $this->firstSchemaNode($row->schema_json ?? null);

        if ($node !== null) {
            $values += $this->schemaValues($node);
        }

        // The historical `locality` is the same place the brief calls `address_locality`.
        if (! array_key_exists('address_locality', $values)) {
            $locality = $this->firstString($row->locality ?? null);

            if ($locality !== null) {
                $values['address_locality'] = $locality;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function schemaValues(array $node): array
    {
        $values = [];

        $type = $this->firstString($node['@type'] ?? null);

        if ($type !== null) {
            $values['schema_type'] = $type;
        }

        $name = $this->firstString($node['name'] ?? null);

        if ($name !== null) {
            $values['business_name'] = $name;
        }

        $address = is_array($node['address'] ?? null) ? $node['address'] : [];

        foreach (self::ADDRESS_COLUMNS as $source => $column) {
            $value = $this->firstString($address[$source] ?? null);

            if ($value !== null) {
                $values[$column] = $value;
            }
        }

        $geo = is_array($node['geo'] ?? null) ? $node['geo'] : [];

        foreach (['latitude', 'longitude'] as $coordinate) {
            $value = $this->firstString($geo[$coordinate] ?? ($node[$coordinate] ?? null));

            if ($value !== null && is_numeric($value)) {
                $values[$coordinate] = (float) $value;
            }
        }

        $encodedLanguages = json_encode($this->stringList($node['availableLanguage'] ?? null));

        if (is_string($encodedLanguages) && $encodedLanguages !== '[]') {
            $values['available_languages'] = $encodedLanguages;
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function socialUrls(mixed $json): array
    {
        $profiles = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($profiles)) {
            return [];
        }

        $urls = [];

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $platform = strtolower($this->firstString($profile['platform'] ?? null) ?? '');
            $column = self::SOCIAL_COLUMNS[$platform] ?? null;
            $url = $this->absoluteUrl($profile['url'] ?? null);

            if ($column === null || $url === null || isset($urls[$column])) {
                continue;
            }

            $urls[$column] = $url;
        }

        return $urls;
    }

    /**
     * Structured data may be a single node, a list of nodes or a `@graph` document.
     *
     * @return array<string, mixed>|null
     */
    private function firstSchemaNode(mixed $json): ?array
    {
        $data = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($data) || $data === []) {
            return null;
        }

        $nodes = array_is_list($data)
            ? $data
            : (is_array($data['@graph'] ?? null) ? $data['@graph'] : [$data]);

        foreach ($nodes as $node) {
            if (is_array($node) && $node !== []) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $string = $this->firstString($item);

            if ($string !== null && ! in_array($string, $items, true)) {
                $items[] = $string;
            }
        }

        return $items;
    }

    private function firstString(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $string = $this->firstString($item);

                if ($string !== null) {
                    return $string;
                }
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function absoluteUrl(mixed $value): ?string
    {
        $value = $this->firstString($value);

        if ($value === null) {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true) ? $value : null;
    }
};
