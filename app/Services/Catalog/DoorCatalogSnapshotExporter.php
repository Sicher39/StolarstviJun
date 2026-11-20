<?php

namespace App\Services\Catalog;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Writes an offline snapshot of the door catalog: the catalog tables as one JSON manifest, the bytes
 * of every owned media original and a checksum file covering both.
 *
 * The bundle is a plain directory, so the source installation never has to be reachable from the
 * target. The exporter is strictly read only: it opens the configured database connection and the
 * configured media disks and writes nothing but the bundle.
 *
 * The bundle is meant to carry the local database. A connection that points at another machine is
 * refused before the first query, unless the caller opts in explicitly: a snapshot taken by accident
 * from a remote server would silently ship foreign data to the target.
 *
 * Only the catalog is exported. Inquiries, preview scenes and every other media owner stay out.
 */
class DoorCatalogSnapshotExporter
{
    /**
     * @return array{path: string, source: DoorCatalogSnapshotSource, counts: array<string, int>, media_count: int, media_bytes: int}
     */
    public function export(string $destinationPath, bool $allowNonLocal = false): array
    {
        $path = DoorCatalogSnapshot::resolveBundlePath($destinationPath);

        if (file_exists($path) && ! is_dir($path)) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Cesta %s existuje a není adresář.', $path));
        }

        if (is_dir($path) && ! $this->isEmptyDirectory($path)) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Adresář %s není prázdný, zvolte prosím novou cestu.', $path));
        }

        // The source is resolved and checked before the target directory is created: a wrongly
        // targeted export must not leave anything behind, not even an empty directory.
        $source = $this->source();
        $source->assertExportable($allowNonLocal);

        File::ensureDirectoryExists($path);
        File::ensureDirectoryExists($path.'/'.DoorCatalogSnapshot::MEDIA_DIRECTORY);

        try {
            $tables = $this->readTables();
            $media = $this->exportMedia($path);
            $counts = $this->counts($tables, $media);

            $this->writeJson($path.'/'.DoorCatalogSnapshot::MANIFEST_FILE, $this->manifest($source, $tables, $media, $counts));
            $this->writeChecksums($path, $media);
        } catch (Throwable $exception) {
            // A failed export must not leave a half written bundle behind. Only the directory of this
            // export is removed, nothing outside of it was touched.
            File::deleteDirectory($path, true);

            throw $exception;
        }

        return [
            'path' => $path,
            'source' => $source,
            'counts' => $counts,
            'media_count' => count($media),
            'media_bytes' => (int) array_sum(array_map(fn (array $entry): int => (int) $entry['size'], $media)),
        ];
    }

    /**
     * Where the bundle comes from. The export always reads the connection and the disks of this
     * installation; the command prints these values, so the operator can confirm that the source
     * really is the local database and not a remote one.
     */
    private function source(): DoorCatalogSnapshotSource
    {
        $connection = DB::connection();

        return DoorCatalogSnapshotSource::fromConnectionConfig(
            (string) $connection->getName(),
            (array) $connection->getConfig(),
        );
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function readTables(): array
    {
        $tables = [];

        // Raw query builder rows keep the stored value of every column, so no cast, mutator or
        // accessor of this installation can change what the target receives.
        foreach (array_keys(DoorCatalogSnapshot::COLUMNS) as $table) {
            $tables[$table] = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
        }

        $this->assertStableKeys($tables);

        return $tables;
    }

    /**
     * The import maps source ids to destination ids through these keys, so a missing or duplicated
     * value would silently merge two different records into one.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private function assertStableKeys(array $tables): void
    {
        $problems = [];
        $modelIds = [];
        $modelSlugs = [];

        foreach ($tables['door_models'] as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $id = (int) $row['id'];

            if ($slug === '') {
                $problems[] = sprintf('Model dveří id %d nemá slug.', $id);

                continue;
            }

            if (isset($modelSlugs[$slug])) {
                $problems[] = sprintf('Dva modely dveří mají stejný slug "%s".', $slug);
            }

            $modelIds[$id] = true;
            $modelSlugs[$slug] = true;
        }

        foreach (['decors', 'glass_types', 'surcharges'] as $table) {
            $codes = [];

            foreach ($tables[$table] as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                $id = (int) $row['id'];

                if ($code === '') {
                    $problems[] = sprintf('%s id %d nemá kód.', DoorCatalogSnapshot::TABLE_LABELS[$table], $id);

                    continue;
                }

                if (isset($codes[$code])) {
                    $problems[] = sprintf('Dva záznamy typu %s mají stejný kód "%s".', DoorCatalogSnapshot::TABLE_LABELS[$table], $code);
                }

                $codes[$code] = true;
            }
        }

        $variantKeys = [];

        foreach ($tables['door_variants'] as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $modelId = (int) $row['door_model_id'];

            if ($code === '') {
                $problems[] = sprintf('Varianta id %d nemá kód.', (int) $row['id']);

                continue;
            }

            if (! isset($modelIds[$modelId])) {
                $problems[] = sprintf('Varianta "%s" odkazuje na neexistující model id %d.', $code, $modelId);
            }

            $key = $modelId.':'.$code;

            if (isset($variantKeys[$key])) {
                $problems[] = sprintf('Dvě varianty modelu id %d mají stejný kód "%s".', $modelId, $code);
            }

            $variantKeys[$key] = true;
        }

        if ($problems !== []) {
            throw DoorCatalogSnapshotException::invalid(
                'Katalog v této databázi nemá jednoznačné klíče, snapshot by nebylo možné bezpečně naimportovat.',
                $problems,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportMedia(string $bundlePath): array
    {
        $entries = [];
        $mediaDirectory = $bundlePath.'/'.DoorCatalogSnapshot::MEDIA_DIRECTORY;

        $this->assertEveryCollectionTravels();

        foreach ($this->mediaOwners() as [$owner, $ownerType, $ownerReference]) {
            $mediaByCollection = $owner->media->groupBy('collection_name');

            foreach (DoorCatalogSnapshot::MEDIA_COLLECTIONS[$ownerType] as $collectionName) {
                foreach ($mediaByCollection->get($collectionName, []) as $media) {
                    $entries[] = $this->exportMediaItem($media, $owner, $ownerType, $ownerReference, $collectionName, $mediaDirectory);
                }
            }
        }

        return $entries;
    }

    /**
     * The snapshot promises to carry every owned media layer of the four catalog models. A collection
     * added to a model without being listed for the transfer would be left behind silently, so the
     * export refuses to run instead of producing an incomplete bundle.
     */
    private function assertEveryCollectionTravels(): void
    {
        /** @var array<string, Model&HasMedia> $models */
        $models = [
            'door_model' => new DoorModel(),
            'door_variant' => new DoorVariant(),
            'decor' => new Decor(),
            'glass_type' => new GlassType(),
        ];

        foreach ($models as $ownerType => $owner) {
            $registered = $owner->getRegisteredMediaCollections()->keys()->all();
            $missing = array_values(array_diff($registered, DoorCatalogSnapshot::MEDIA_COLLECTIONS[$ownerType]));

            if ($missing !== []) {
                throw DoorCatalogSnapshotException::invalid(sprintf(
                    'Model %s registruje kolekce, které snapshot nepřenáší: %s.',
                    $ownerType,
                    implode(', ', $missing),
                ));
            }
        }
    }

    /**
     * Only the four catalog models are yielded, together with the stable key that identifies them on
     * the target installation.
     *
     * @return iterable<int, array{0: DoorModel|DoorVariant|Decor|GlassType, 1: string, 2: array<string, string>}>
     */
    private function mediaOwners(): iterable
    {
        foreach (DoorModel::query()->with('media')->orderBy('id')->lazy(100) as $doorModel) {
            yield [$doorModel, 'door_model', ['slug' => (string) $doorModel->slug]];
        }

        foreach (DoorVariant::query()->with(['media', 'doorModel'])->orderBy('id')->lazy(100) as $variant) {
            $modelSlug = (string) $variant->doorModel?->slug;

            if ($modelSlug === '') {
                throw DoorCatalogSnapshotException::invalid(sprintf(
                    'Varianta id %d nemá dostupný model pro přenos médií.',
                    (int) $variant->getKey(),
                ));
            }

            yield [$variant, 'door_variant', ['model_slug' => $modelSlug, 'code' => (string) $variant->code]];
        }

        foreach (Decor::query()->with('media')->orderBy('id')->lazy(100) as $decor) {
            yield [$decor, 'decor', ['code' => (string) $decor->code]];
        }

        foreach (GlassType::query()->with('media')->orderBy('id')->lazy(100) as $glassType) {
            yield [$glassType, 'glass_type', ['code' => (string) $glassType->code]];
        }
    }

    /**
     * @param  array<string, string>  $ownerReference
     * @return array<string, mixed>
     */
    private function exportMediaItem(
        Media $media,
        Model&HasMedia $owner,
        string $ownerType,
        array $ownerReference,
        string $collectionName,
        string $mediaDirectory,
    ): array {
        $sourcePath = $this->mediaSourcePath($media);
        $fileName = sprintf('%d.%s', (int) $media->getKey(), $this->mediaExtension($media));
        $targetPath = $mediaDirectory.'/'.$fileName;

        if (! File::copy($sourcePath, $targetPath)) {
            throw DoorCatalogSnapshotException::invalid(sprintf(
                'Soubor média %d se nepodařilo zkopírovat do snapshotu.',
                (int) $media->getKey(),
            ));
        }

        return [
            'source_id' => (int) $media->getKey(),
            'owner' => ['model' => $ownerType, ...$ownerReference],
            'owner_id' => (int) $owner->getKey(),
            'collection' => $collectionName,
            'name' => (string) $media->name,
            'file_name' => (string) $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => (int) $media->size,
            'disk' => (string) $media->disk,
            'conversions_disk' => $media->conversions_disk,
            'order_column' => $media->order_column === null ? null : (int) $media->order_column,
            'manipulations' => (array) $media->manipulations,
            'custom_properties' => (array) $media->custom_properties,
            'generated_conversions' => (array) $media->generated_conversions,
            'responsive_images' => (array) $media->responsive_images,
            'file' => DoorCatalogSnapshot::MEDIA_DIRECTORY.'/'.$fileName,
            'sha256' => (string) hash_file('sha256', $targetPath),
        ];
    }

    private function mediaSourcePath(Media $media): string
    {
        $diskName = (string) $media->disk;

        if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
            throw DoorCatalogSnapshotException::invalid(sprintf(
                'Médium %d je na disku "%s", který není lokální. Snapshot podporuje jen lokální disky.',
                (int) $media->getKey(),
                $diskName,
            ));
        }

        $relativePath = $media->getPathRelativeToRoot();
        $absolutePath = Storage::disk($diskName)->path($relativePath);

        if (! is_file($absolutePath)) {
            throw DoorCatalogSnapshotException::invalid(sprintf(
                'Soubor média %d chybí na disku "%s" (%s).',
                (int) $media->getKey(),
                $diskName,
                $relativePath,
            ));
        }

        return $absolutePath;
    }

    private function mediaExtension(Media $media): string
    {
        $extension = strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,12}$/', $extension) === 1 ? $extension : 'bin';
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @param  list<array<string, mixed>>  $media
     * @return array<string, int>
     */
    private function counts(array $tables, array $media): array
    {
        $counts = [];

        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        $counts['media'] = count($media);

        return $counts;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @param  list<array<string, mixed>>  $media
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function manifest(DoorCatalogSnapshotSource $source, array $tables, array $media, array $counts): array
    {
        return [
            'format' => DoorCatalogSnapshot::FORMAT,
            'format_version' => DoorCatalogSnapshot::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => 'catalog:export-door-snapshot',
            'source' => [
                ...$source->toArray(),
                'app_url' => config('app.url'),
                'laravel_version' => app()->version(),
            ],
            'counts' => $counts,
            'tables' => $tables,
            'media' => $media,
            'notes' => [
                'Konverze a responzivní obrázky médií se nepřenášejí, po importu je vytvořte příkazem: php artisan medialibrary:regenerate',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $media
     */
    private function writeChecksums(string $bundlePath, array $media): void
    {
        $files = [
            DoorCatalogSnapshot::MANIFEST_FILE => (string) hash_file(
                'sha256',
                $bundlePath.'/'.DoorCatalogSnapshot::MANIFEST_FILE,
            ),
        ];

        foreach ($media as $entry) {
            $files[(string) $entry['file']] = (string) $entry['sha256'];
        }

        $this->writeJson($bundlePath.'/'.DoorCatalogSnapshot::CHECKSUMS_FILE, [
            'algorithm' => 'sha256',
            'files' => $files,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        if (File::put($path, $json."\n") === false) {
            throw DoorCatalogSnapshotException::invalid(sprintf('Soubor %s se nepodařilo zapsat.', $path));
        }
    }

    private function isEmptyDirectory(string $path): bool
    {
        return File::files($path) === [] && File::directories($path) === [];
    }
}
