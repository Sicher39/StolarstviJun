<?php

namespace App\Services\Catalog;

use JsonException;

/**
 * A validated, read only view of a door catalog snapshot bundle.
 *
 * A bundle is a directory with three parts:
 *
 *   manifest.json   the catalog tables, the media metadata and the stable keys used for mapping
 *   checksums.json  sha256 of every file in the bundle, manifest.json included
 *   media/          the original bytes of every owned media file, named after the source media id
 *
 * The constructor is private: a snapshot can only exist after the checksums, the manifest schema,
 * the media collections and every relative path were validated. The importer therefore never has to
 * re-check whether it is about to read something the exporter did not write.
 */
class DoorCatalogSnapshot
{
    public const FORMAT = 'door-catalog-snapshot';

    public const FORMAT_VERSION = 1;

    public const MANIFEST_FILE = 'manifest.json';

    public const CHECKSUMS_FILE = 'checksums.json';

    public const MEDIA_DIRECTORY = 'media';

    /**
     * The exact columns the target installation writes. Any other column the source database may
     * have is informational only, so a snapshot can be imported into a slightly different schema.
     */
    public const COLUMNS = [
        'door_models' => [
            'id',
            'name',
            'slug',
            'category',
            'description',
            'base_price_without_vat',
            'base_price_with_vat',
            'active',
            'default_variant_id',
            'default_decor_id',
            'created_at',
            'updated_at',
        ],
        'door_variants' => [
            'id',
            'door_model_id',
            'code',
            'width',
            'height',
            'opening_direction',
            'opening_type',
            'has_glass',
            'sliding_possible',
            'price_modifier',
            'canvas_width',
            'canvas_height',
            'created_at',
            'updated_at',
        ],
        'decors' => [
            'id',
            'name',
            'code',
            'price_modifier',
            'active',
            'created_at',
            'updated_at',
        ],
        'glass_types' => [
            'id',
            'name',
            'code',
            'opacity',
            'price_modifier',
            'active',
            'created_at',
            'updated_at',
        ],
        'surcharges' => [
            'id',
            'name',
            'code',
            'price_without_vat',
            'active',
            'created_at',
            'updated_at',
        ],
        'decor_door_model' => [
            'id',
            'decor_id',
            'door_model_id',
            'created_at',
            'updated_at',
        ],
        'door_model_glass_type' => [
            'id',
            'door_model_id',
            'glass_type_id',
            'created_at',
            'updated_at',
        ],
        'door_model_surcharge' => [
            'id',
            'door_model_id',
            'surcharge_id',
            'created_at',
            'updated_at',
        ],
    ];

    /**
     * Media collections per owner type. Only these collections of these four models travel with the
     * snapshot; inquiries, preview scenes and every other media owner stay untouched.
     *
     * @var array<string, list<string>>
     */
    public const MEDIA_COLLECTIONS = [
        'door_model' => ['preview_image', 'gallery'],
        'door_variant' => [
            'frame_base',
            'frame_mask',
            'door_mask',
            'door_vertical_decor_mask',
            'door_horizontal_decor_mask',
            'frame_vertical_decor_mask',
            'frame_horizontal_decor_mask',
            'depth_overlay',
            'construction_overlay',
            'handle_overlay',
            'source_reference',
        ],
        'decor' => ['texture_image', 'preview_image'],
        'glass_type' => ['texture_image', 'preview_image'],
    ];

    /**
     * Human readable labels used in the reports and in the conflict list.
     *
     * @var array<string, string>
     */
    public const TABLE_LABELS = [
        'door_models' => 'model dveří',
        'door_variants' => 'varianta dveří',
        'decors' => 'dekor',
        'glass_types' => 'sklo',
        'surcharges' => 'příplatek',
        'decor_door_model' => 'vazba dekor–model',
        'door_model_glass_type' => 'vazba model–sklo',
        'door_model_surcharge' => 'vazba model–příplatek',
        'media' => 'média',
    ];

    /**
     * Keys the importer needs in every media entry before it touches the destination.
     *
     * @var list<string>
     */
    private const MEDIA_ENTRY_KEYS = [
        'source_id',
        'owner',
        'owner_id',
        'collection',
        'name',
        'file_name',
        'size',
        'order_column',
        'manipulations',
        'custom_properties',
        'file',
        'sha256',
    ];

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $mediaFiles  bundle relative path => absolute path
     */
    private function __construct(
        private readonly string $path,
        private readonly array $manifest,
        private readonly array $mediaFiles,
    ) {}

    /**
     * Loads and fully validates a bundle. Any problem is thrown before the importer can write.
     */
    public static function load(string $bundlePath): self
    {
        $path = self::resolveBundlePath($bundlePath);

        if (! is_dir($path)) {
            throw DoorCatalogSnapshotException::bundleNotFound($path);
        }

        $checksums = self::readJson(self::pathFor($path, self::CHECKSUMS_FILE));
        $hashes = $checksums['files'] ?? null;

        if (! is_array($hashes) || $hashes === []) {
            throw DoorCatalogSnapshotException::invalid('Soubor checksums.json neobsahuje žádné kontrolní součty.');
        }

        if (($checksums['algorithm'] ?? 'sha256') !== 'sha256') {
            throw DoorCatalogSnapshotException::invalid('Snapshot používá nepodporovaný kontrolní algoritmus.');
        }

        foreach ($hashes as $relativePath => $expectedHash) {
            $absolutePath = self::resolveBundleFile($path, (string) $relativePath);
            $actualHash = hash_file('sha256', $absolutePath);

            if (! is_string($expectedHash) || ! is_string($actualHash) || ! hash_equals(strtolower($expectedHash), $actualHash)) {
                throw DoorCatalogSnapshotException::invalid(sprintf(
                    'Kontrolní součet nesouhlasí, snapshot je poškozený nebo upravený: %s',
                    (string) $relativePath,
                ));
            }
        }

        $manifest = self::readJson(self::pathFor($path, self::MANIFEST_FILE));

        self::assertManifest($manifest);

        /** @var array<string, string> $mediaFiles */
        $mediaFiles = [];
        $mediaEntries = $manifest['media'];

        foreach ($mediaEntries as $entry) {
            $relativePath = (string) $entry['file'];

            if (! array_key_exists($relativePath, $hashes)) {
                throw DoorCatalogSnapshotException::invalid(sprintf(
                    'Soubor média %s není uvedený v checksums.json.',
                    $relativePath,
                ));
            }

            if (! hash_equals(strtolower((string) $hashes[$relativePath]), strtolower((string) $entry['sha256']))) {
                throw DoorCatalogSnapshotException::invalid(sprintf(
                    'Kontrolní součet média %s v manifestu nesouhlasí s checksums.json.',
                    $relativePath,
                ));
            }

            $mediaFiles[$relativePath] = self::resolveBundleFile($path, $relativePath);
        }

        return new self($path, $manifest, $mediaFiles);
    }

    /**
     * Paths are taken from the command line, so a relative path is resolved against the project root
     * instead of the current working directory of the shell.
     */
    public static function resolveBundlePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw DoorCatalogSnapshotException::invalid('Cesta ke snapshotu nesmí být prázdná.');
        }

        $normalized = str_replace('\\', '/', $path);

        if (preg_match('#^(?:[A-Za-z]:/|/)#', $normalized) !== 1) {
            $normalized = str_replace('\\', '/', base_path($normalized));
        }

        return rtrim($normalized, '/');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function generatedAt(): string
    {
        return (string) ($this->manifest['generated_at'] ?? '');
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (array_keys(self::COLUMNS) as $table) {
            $counts[$table] = count($this->rows($table));
        }

        $counts['media'] = count($this->mediaEntries());

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->manifest['tables'][$table];

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mediaEntries(): array
    {
        /** @var list<array<string, mixed>> $entries */
        $entries = $this->manifest['media'];

        return $entries;
    }

    public function mediaFile(string $relativePath): string
    {
        if (! isset($this->mediaFiles[$relativePath])) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Soubor média %s ve snapshotu chybí.', $relativePath));
        }

        return $this->mediaFiles[$relativePath];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private static function assertManifest(array $manifest): void
    {
        if (($manifest['format'] ?? null) !== self::FORMAT) {
            throw DoorCatalogSnapshotException::invalid('Soubor manifest.json není snapshot katalogu dveří.');
        }

        if ((int) ($manifest['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            throw DoorCatalogSnapshotException::invalid(sprintf(
                'Nepodporovaná verze snapshotu %s, tento příkaz umí verzi %d.',
                (string) ($manifest['format_version'] ?? '?'),
                self::FORMAT_VERSION,
            ));
        }

        $tables = $manifest['tables'] ?? null;

        if (! is_array($tables)) {
            throw DoorCatalogSnapshotException::invalid('Soubor manifest.json neobsahuje tabulky katalogu.');
        }

        foreach (self::COLUMNS as $table => $columns) {
            $rows = $tables[$table] ?? null;

            if (! is_array($rows)) {
                throw DoorCatalogSnapshotException::invalid(sprintf('V manifest.json chybí tabulka %s.', $table));
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw DoorCatalogSnapshotException::invalid(sprintf('Tabulka %s obsahuje neplatný řádek.', $table));
                }

                foreach ($columns as $column) {
                    if (! array_key_exists($column, $row)) {
                        throw DoorCatalogSnapshotException::invalid(sprintf(
                            'V tabulce %s chybí sloupec %s.',
                            $table,
                            $column,
                        ));
                    }
                }
            }
        }

        $media = $manifest['media'] ?? null;

        if (! is_array($media) || ! array_is_list($media)) {
            throw DoorCatalogSnapshotException::invalid('Soubor manifest.json neobsahuje seznam médií.');
        }

        foreach ($media as $entry) {
            self::assertMediaEntry($entry);
        }
    }

    private static function assertMediaEntry(mixed $entry): void
    {
        if (! is_array($entry)) {
            throw DoorCatalogSnapshotException::invalid('Seznam médií v manifestu obsahuje neplatnou položku.');
        }

        foreach (self::MEDIA_ENTRY_KEYS as $key) {
            if (! array_key_exists($key, $entry)) {
                throw DoorCatalogSnapshotException::invalid(sprintf('U média v manifestu chybí údaj %s.', $key));
            }
        }

        $owner = $entry['owner'];

        if (! is_array($owner) || ! is_string($owner['model'] ?? null)) {
            throw DoorCatalogSnapshotException::invalid('U média v manifestu chybí typ vlastníka.');
        }

        $collections = self::MEDIA_COLLECTIONS[$owner['model']] ?? null;

        if ($collections === null) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Neznámý typ vlastníka média: %s', $owner['model']));
        }

        if (! in_array((string) $entry['collection'], $collections, true)) {
            throw DoorCatalogSnapshotException::invalid(sprintf(
                'Kolekce %s nepatří k typu %s.',
                (string) $entry['collection'],
                $owner['model'],
            ));
        }

        // The stable key of the owner must be present too, otherwise the media could not be attached.
        $requiredOwnerKeys = match ($owner['model']) {
            'door_variant' => ['model', 'model_slug', 'code'],
            'door_model' => ['model', 'slug'],
            default => ['model', 'code'],
        };

        foreach ($requiredOwnerKeys as $key) {
            if (! is_string($owner[$key] ?? null) || trim((string) $owner[$key]) === '') {
                throw DoorCatalogSnapshotException::invalid(sprintf('U média v manifestu chybí klíč vlastníka %s.', $key));
            }
        }

        $relativePath = $entry['file'];

        if (! is_string($relativePath) || ! str_starts_with($relativePath, self::MEDIA_DIRECTORY.'/')) {
            throw DoorCatalogSnapshotException::unsafePath(is_string($relativePath) ? $relativePath : '(neplatná cesta)');
        }

        self::assertSafeRelativePath($relativePath);

        if (! is_string($entry['sha256']) || preg_match('/^[a-f0-9]{64}$/', strtolower($entry['sha256'])) !== 1) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Médium %s nemá platný kontrolní součet.', $relativePath));
        }
    }

    /**
     * A bundle path may never leave the bundle: no absolute path, no drive letter, no `..` segment and
     * no backslash, so a hand edited manifest cannot read or write outside of the snapshot directory.
     */
    private static function assertSafeRelativePath(string $relativePath): void
    {
        $isUnsafe = $relativePath === ''
            || str_contains($relativePath, '\\')
            || str_contains($relativePath, "\0")
            || str_starts_with($relativePath, '/')
            || preg_match('#^[A-Za-z]:#', $relativePath) === 1
            || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1
            || preg_match('#^[A-Za-z0-9._\-/]+$#', $relativePath) !== 1;

        if ($isUnsafe) {
            throw DoorCatalogSnapshotException::unsafePath($relativePath);
        }
    }

    private static function resolveBundleFile(string $bundlePath, string $relativePath): string
    {
        self::assertSafeRelativePath($relativePath);

        $absolutePath = $bundlePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($absolutePath)) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Ve snapshotu chybí soubor %s.', $relativePath));
        }

        $realPath = realpath($absolutePath);
        $realBundlePath = realpath($bundlePath);

        if ($realPath === false || $realBundlePath === false || ! self::isInside($realPath, $realBundlePath)) {
            throw DoorCatalogSnapshotException::unsafePath($relativePath);
        }

        return $realPath;
    }

    private static function isInside(string $path, string $directory): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedDirectory = rtrim(str_replace('\\', '/', $directory), '/');

        return str_starts_with($normalizedPath, $normalizedDirectory.'/');
    }

    private static function pathFor(string $bundlePath, string $fileName): string
    {
        $path = $bundlePath.'/'.$fileName;

        if (! is_file($path)) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Snapshotu chybí soubor %s.', $fileName));
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Soubor %s se nepodařilo přečíst.', basename($path)));
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw DoorCatalogSnapshotException::invalid(sprintf(
                'Soubor %s není platný JSON: %s',
                basename($path),
                $exception->getMessage(),
            ));
        }

        if (! is_array($decoded)) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Soubor %s neobsahuje objekt.', basename($path)));
        }

        return $decoded;
    }
}
