<?php

namespace Tests\Unit;

use App\Services\Catalog\DoorCatalogSnapshot;
use App\Services\Catalog\DoorCatalogSnapshotException;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * A bundle directory is the only input of the import, so reading it is the guard that keeps a
 * damaged or hand edited bundle away from the destination database.
 *
 * The test never touches a database, a media disk or the application container: it builds bundles in
 * a throw away directory under storage/framework/testing and only exercises
 * DoorCatalogSnapshot::load(), which reads files and nothing else. It is therefore safe to run on a
 * developer machine whose .env points at a real database.
 *
 * The exporter and the importer themselves are not covered here, because they need catalog tables
 * and media disks and the current phpunit.xml does not define an isolated connection.
 */
class DoorCatalogSnapshotBundleTest extends TestCase
{
    private const MEDIA_BYTES = "\x89PNG\r\n\x1a\n-snapshot-media";

    private Filesystem $files;

    private string $bundlePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->bundlePath = dirname(__DIR__, 2).'/storage/framework/testing/door-catalog-bundle-'.uniqid();

        $this->files->ensureDirectoryExists($this->bundlePath.'/'.DoorCatalogSnapshot::MEDIA_DIRECTORY);
    }

    protected function tearDown(): void
    {
        if ($this->bundlePath !== '') {
            $this->files->deleteDirectory($this->bundlePath);
        }

        parent::tearDown();
    }

    public function test_a_valid_bundle_exposes_its_rows_media_and_counts(): void
    {
        $this->writeBundle();

        $snapshot = DoorCatalogSnapshot::load($this->bundlePath);

        // resolveBundlePath() normalises the separators, which is all that changes on Windows.
        $this->assertSame(str_replace('\\', '/', $this->bundlePath), $snapshot->path());
        $this->assertSame('2026-03-19T10:00:00+00:00', $snapshot->generatedAt());

        $models = $snapshot->rows('door_models');

        $this->assertCount(1, $models);
        $this->assertSame('model-a', $models[0]['slug']);
        $this->assertSame(10, $models[0]['id']);

        $counts = $snapshot->counts();

        $this->assertSame(1, $counts['door_models']);
        $this->assertSame(1, $counts['door_variants']);
        $this->assertSame(0, $counts['glass_types']);
        $this->assertSame(1, $counts['media']);

        $media = $snapshot->mediaEntries();

        $this->assertCount(1, $media);
        $this->assertSame('preview_image', $media[0]['collection']);
        $this->assertFileExists($snapshot->mediaFile('media/1.png'));
        $this->assertSame(self::MEDIA_BYTES, $this->files->get($snapshot->mediaFile('media/1.png')));
    }

    public function test_a_missing_bundle_directory_is_rejected(): void
    {
        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('Adresář se snapshotem neexistuje');

        DoorCatalogSnapshot::load($this->bundlePath.'/missing');
    }

    public function test_a_tampered_media_file_is_rejected_before_anything_is_read(): void
    {
        $this->writeBundle();

        $this->files->append($this->bundlePath.'/media/1.png', 'tampered');

        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('Kontrolní součet nesouhlasí');

        DoorCatalogSnapshot::load($this->bundlePath);
    }

    public function test_a_media_file_listed_in_the_checksums_must_exist(): void
    {
        $this->writeBundle();

        $this->files->delete($this->bundlePath.'/media/1.png');

        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('Ve snapshotu chybí soubor media/1.png');

        DoorCatalogSnapshot::load($this->bundlePath);
    }

    public function test_a_bundle_without_one_of_the_catalog_tables_is_rejected(): void
    {
        $manifest = $this->manifest();
        unset($manifest['tables']['glass_types']);

        $this->writeBundle(['manifest' => $manifest]);

        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('V manifest.json chybí tabulka glass_types');

        DoorCatalogSnapshot::load($this->bundlePath);
    }

    public function test_an_unsupported_format_version_is_rejected(): void
    {
        $manifest = $this->manifest();
        $manifest['format_version'] = DoorCatalogSnapshot::FORMAT_VERSION + 1;

        $this->writeBundle(['manifest' => $manifest]);

        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('Nepodporovaná verze snapshotu');

        DoorCatalogSnapshot::load($this->bundlePath);
    }

    public function test_a_media_path_that_leaves_the_bundle_is_rejected(): void
    {
        $manifest = $this->manifest();
        $manifest['media'][0]['file'] = 'media/../evil.png';

        $this->writeBundle(['manifest' => $manifest]);

        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('Snapshot obsahuje nepovolenou cestu');

        DoorCatalogSnapshot::load($this->bundlePath);
    }

    public function test_a_collection_that_does_not_belong_to_the_owner_type_is_rejected(): void
    {
        $manifest = $this->manifest();
        // preview_image is registered on door models, decors and glass types, never on variants.
        $manifest['media'][0]['owner'] = ['model' => 'door_variant', 'model_slug' => 'model-a', 'code' => 'BASE'];

        $this->writeBundle(['manifest' => $manifest]);

        $this->expectException(DoorCatalogSnapshotException::class);
        $this->expectExceptionMessage('Kolekce preview_image nepatří k typu door_variant');

        DoorCatalogSnapshot::load($this->bundlePath);
    }

    /**
     * @param  array{manifest?: array<string, mixed>}  $overrides
     */
    private function writeBundle(array $overrides = []): void
    {
        $manifestJson = $this->encode($overrides['manifest'] ?? $this->manifest());

        $this->files->put($this->bundlePath.'/'.DoorCatalogSnapshot::MANIFEST_FILE, $manifestJson);
        $this->files->put($this->bundlePath.'/media/1.png', self::MEDIA_BYTES);

        $this->files->put($this->bundlePath.'/'.DoorCatalogSnapshot::CHECKSUMS_FILE, $this->encode([
            'algorithm' => 'sha256',
            'files' => [
                DoorCatalogSnapshot::MANIFEST_FILE => hash('sha256', $manifestJson),
                'media/1.png' => hash('sha256', self::MEDIA_BYTES),
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $tables = [];

        foreach (array_keys(DoorCatalogSnapshot::COLUMNS) as $table) {
            $tables[$table] = [];
        }

        $tables['door_models'][] = $this->row('door_models', [
            'id' => 10,
            'name' => 'Model A',
            'slug' => 'model-a',
            'category' => 'interior',
            'active' => 1,
        ]);

        $tables['door_variants'][] = $this->row('door_variants', [
            'id' => 20,
            'door_model_id' => 10,
            'code' => 'BASE',
        ]);

        return [
            'format' => DoorCatalogSnapshot::FORMAT,
            'format_version' => DoorCatalogSnapshot::FORMAT_VERSION,
            'generated_at' => '2026-03-19T10:00:00+00:00',
            'generated_by' => 'catalog:export-door-snapshot',
            'source' => [
                'connection' => 'sqlite',
                'driver' => 'sqlite',
                'database' => ':memory:',
                'host' => null,
            ],
            'counts' => ['door_models' => 1, 'door_variants' => 1, 'media' => 1],
            'tables' => $tables,
            'media' => [$this->mediaEntry()],
            'notes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function row(string $table, array $values): array
    {
        $row = [];

        foreach (DoorCatalogSnapshot::COLUMNS[$table] as $column) {
            $row[$column] = $values[$column] ?? null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaEntry(): array
    {
        return [
            'source_id' => 1,
            'owner' => ['model' => 'door_model', 'slug' => 'model-a'],
            'owner_id' => 10,
            'collection' => 'preview_image',
            'name' => 'Náhled',
            'file_name' => 'nahled.png',
            'mime_type' => 'image/png',
            'size' => strlen(self::MEDIA_BYTES),
            'disk' => 'public',
            'conversions_disk' => 'public',
            'order_column' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'file' => 'media/1.png',
            'sha256' => hash('sha256', self::MEDIA_BYTES),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}
