<?php

namespace App\Console\Commands;

use App\Services\Catalog\DoorCatalogSnapshot;
use App\Services\Catalog\DoorCatalogSnapshotException;
use App\Services\Catalog\DoorCatalogSnapshotExporter;
use App\Services\Catalog\DoorCatalogSnapshotSource;
use Illuminate\Console\Command;
use Throwable;

/**
 * Source side of the offline catalog transfer. The command reads only the database this
 * installation is configured for and only the media of the catalog models, then writes a new
 * directory with a JSON manifest, the original media bytes and a checksum file. The resolved
 * connection and host are printed, so a snapshot can never be mistaken for a local one silently.
 *
 * A database that is configured on another machine is refused before anything is written: the
 * snapshot is meant to carry the local catalog, and a remote export would have to be requested on
 * purpose, with --allow-nonlocal and an explicit warning.
 *
 * Nothing is written to the database and no existing snapshot is overwritten: a non empty target
 * directory is refused, so an operator never loses the bundle of an earlier transfer by accident.
 *
 *   php artisan catalog:export-door-snapshot storage/app/catalog-snapshot-2026-03-19
 */
class ExportDoorCatalogSnapshot extends Command
{
    protected $signature = 'catalog:export-door-snapshot
        {path : Nový adresář pro snapshot (relativně ke kořeni projektu)}
        {--allow-nonlocal : Povolí export i z databáze, která neběží na tomto počítači (nedoporučeno)}';

    protected $description = 'Vytvoří offline snapshot katalogu dveří (data, média a kontrolní součty) z lokální databáze.';

    public function handle(DoorCatalogSnapshotExporter $exporter): int
    {
        try {
            $result = $exporter->export(
                (string) $this->argument('path'),
                (bool) $this->option('allow-nonlocal'),
            );
        } catch (DoorCatalogSnapshotException $exception) {
            $this->error($exception->getMessage());
            $this->printDetails($exception->details);

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Snapshot se nepodařilo vytvořit: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Snapshot katalogu dveří byl vytvořen:');
        $this->line('  '.$result['path']);
        $this->newLine();

        $this->printSource($result['source']);
        $this->newLine();

        $this->table(
            ['Tabulka', 'Záznamů'],
            collect($result['counts'])
                ->map(fn (int $count, string $table): array => [DoorCatalogSnapshot::TABLE_LABELS[$table] ?? $table, (string) $count])
                ->values()
                ->all(),
        );

        $this->line(sprintf(
            'Mediální soubory: %d (%s).',
            $result['media_count'],
            $this->formatBytes($result['media_bytes']),
        ));
        $this->newLine();
        $this->comment('Přeneste celý adresář do cílové instalace a tam spusťte:');
        $this->line(sprintf('  php artisan catalog:import-door-snapshot %s --dry-run', $result['path']));

        return self::SUCCESS;
    }

    /**
     * The operator has to see where the data came from: the command only ever reads the connection
     * and the disks this installation is configured for. A remote host therefore means the bundle is
     * remote too, which is exactly what the export refuses by default and reports here when the
     * operator overrode the refusal.
     */
    private function printSource(DoorCatalogSnapshotSource $source): void
    {
        $this->line('Zdroj: '.$source->describe().'.');

        if ($source->isLocal()) {
            return;
        }

        $this->warn('POZOR: databáze neběží na tomto počítači, snapshot neobsahuje lokální data.');
        $this->warn('Export povolil parametr --allow-nonlocal. Před importem ověřte, odkud data pocházejí.');
    }

    /**
     * @param  list<string>  $details
     */
    private function printDetails(array $details): void
    {
        foreach (array_slice($details, 0, 20) as $detail) {
            $this->line('  - '.$detail);
        }

        if (count($details) > 20) {
            $this->line(sprintf('  … a dalších %d problémů', count($details) - 20));
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' kB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
