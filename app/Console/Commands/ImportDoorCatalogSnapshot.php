<?php

namespace App\Console\Commands;

use App\Services\Catalog\DoorCatalogSnapshot;
use App\Services\Catalog\DoorCatalogSnapshotException;
use App\Services\Catalog\DoorCatalogSnapshotImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Target side of the offline catalog transfer.
 *
 * The command is deliberately not a seeder: a seeder cannot receive an argument or an option, so it
 * could neither be pointed at a concrete bundle nor offer a dry run, and it could be run by accident
 * through `db:seed`. A manual, explicit command keeps the destructive surface at zero: the bundle is
 * validated first, nothing is written without `--force`, and existing catalog records are never
 * deleted or replaced.
 *
 *   php artisan catalog:import-door-snapshot storage/app/catalog-snapshot-2026-03-19 --dry-run
 *   php artisan catalog:import-door-snapshot storage/app/catalog-snapshot-2026-03-19
 */
class ImportDoorCatalogSnapshot extends Command
{
    protected $signature = 'catalog:import-door-snapshot
        {path : Adresář se snapshotem (manifest.json, checksums.json, media/)}
        {--dry-run : Snapshot jen ověří a vypíše plán, nic nezapíše}
        {--force : Povolí zápis i tehdy, když v cíli už existují záznamy se stejnými klíči}';

    protected $description = 'Naimportuje katalog dveří ze snapshotu vytvořeného příkazem catalog:export-door-snapshot.';

    public function handle(DoorCatalogSnapshotImporter $importer): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForced = (bool) $this->option('force');

        try {
            $snapshot = DoorCatalogSnapshot::load((string) $this->argument('path'));
        } catch (DoorCatalogSnapshotException $exception) {
            $this->error($exception->getMessage());
            $this->printDetails($exception->details);

            return self::FAILURE;
        }

        $plan = $importer->plan($snapshot);

        $this->info('Snapshot katalogu dveří:');
        $this->line('  '.$snapshot->path());
        $this->line('  vytvořen '.$snapshot->generatedAt());
        $this->newLine();

        $this->table(
            ['Tabulka', 'Záznamů'],
            collect($plan['counts'])
                ->map(fn (int $count, string $table): array => [DoorCatalogSnapshot::TABLE_LABELS[$table] ?? $table, (string) $count])
                ->values()
                ->all(),
        );

        if ($plan['conflicts'] !== []) {
            $this->warn(sprintf(
                'V cílové databázi už existuje %d záznamů se stejnými stabilními klíči:',
                count($plan['conflicts']),
            ));
            $this->printDetails($plan['conflicts']);
            $this->newLine();
        }

        if ($isDryRun) {
            $this->comment('Kontrola snapshotu proběhla, do databáze ani do médií se nic nezapsalo.');

            if ($plan['conflicts'] !== [] && ! $isForced) {
                $this->comment('Bez --force by import nic nezapsal.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if ($plan['conflicts'] !== [] && ! $isForced) {
            $this->error('Import byl zrušen, v cíli už existují záznamy se stejnými klíči a --force nebyl zadán. Nic nebylo zapsáno.');

            return self::FAILURE;
        }

        if ($isForced) {
            $this->warn('--force: existující záznamy budou přepsány hodnotami ze snapshotu. Nic se nemaže ani neodpojuje.');
            $this->newLine();
        }

        try {
            $report = $importer->import($snapshot, $isForced);
        } catch (DoorCatalogSnapshotException $exception) {
            $this->error($exception->getMessage());
            $this->printDetails($exception->details);

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Import selhal a byl vrácen zpět: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Import katalogu dveří dokončen.');
        $this->newLine();

        $this->table(
            ['Tabulka', 'Nově', 'Aktualizováno'],
            collect($report['created'])
                ->map(fn (int $created, string $table): array => [
                    DoorCatalogSnapshot::TABLE_LABELS[$table] ?? $table,
                    (string) $created,
                    (string) ($report['updated'][$table] ?? 0),
                ])
                ->values()
                ->all(),
        );

        $this->line(sprintf('Vazby přidané do modelů: %d.', $report['relations_attached']));
        $this->line(sprintf('Výchozí volby nastavené u modelů: %d.', $report['defaults']));
        $this->line(sprintf('Média vytvořená: %d, přeskočená: %d.', $report['media_created'], $report['media_skipped']));

        if ($report['warnings'] !== []) {
            $this->newLine();
            $this->warn('Upozornění:');
            $this->printDetails($report['warnings']);
        }

        if ($report['media_created'] > 0) {
            $this->newLine();
            $this->comment('Konverze a responzivní obrázky médií se nepřenášejí, doplňte je příkazem:');
            $this->line('  php artisan medialibrary:regenerate');
        }

        return self::SUCCESS;
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
            $this->line(sprintf('  … a dalších %d položek', count($details) - 20));
        }
    }
}
