<?php

namespace App\Services\Catalog;

use App\Models\Decor;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Hardware;
use App\Models\Surcharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Imports a validated snapshot bundle into this installation.
 *
 * The rules the importer never breaks:
 *
 *  - source ids are never copied, they are translated to destination ids through the stable keys
 *    (model slug, variant code per model, and the code of a decor, glass type, surcharge, frame or
 *    hardware);
 *  - a bundle written before the frame and hardware catalog existed stays importable: the tables and
 *    the door model type it does not carry are simply left untouched;
 *  - a run without `force` aborts as soon as any destination record already uses one of those keys,
 *    so an accidental second run cannot duplicate or overwrite the catalog;
 *  - a conflict free run only creates records, it never deletes or detaches anything, relations are
 *    attached with `syncWithoutDetaching` and existing media is left untouched;
 *  - all database writes happen in one transaction and the media files created by the run are
 *    removed again when the transaction fails.
 *
 * One instance performs one import: the id maps and the report are reset at the beginning of every
 * `import()` call.
 */
class DoorCatalogSnapshotImporter
{
    /** @var array<int, int> source decor id => destination decor id */
    private array $decorIds = [];

    /** @var array<int, int> source glass type id => destination glass type id */
    private array $glassTypeIds = [];

    /** @var array<int, int> source surcharge id => destination surcharge id */
    private array $surchargeIds = [];

    /** @var array<int, int> source door model id => destination door model id */
    private array $doorModelIds = [];

    /** @var array<int, int> source door variant id => destination door variant id */
    private array $doorVariantIds = [];

    /** @var array<int, int> source door frame id => destination door frame id */
    private array $doorFrameIds = [];

    /** @var array<int, int> source hardware id => destination hardware id */
    private array $hardwareIds = [];

    /** @var array<string, mixed> */
    private array $report = [];

    /**
     * Validates the bundle and reports what the import would do, without touching the destination.
     *
     * @return array{conflicts: list<string>, counts: array<string, int>}
     */
    public function plan(DoorCatalogSnapshot $snapshot): array
    {
        return [
            'conflicts' => $this->detectConflicts($snapshot),
            'counts' => $snapshot->counts(),
        ];
    }

    /**
     * Stable keys that already exist in this installation. An empty list means the import can create
     * everything without touching an existing record.
     *
     * @return list<string>
     */
    public function detectConflicts(DoorCatalogSnapshot $snapshot): array
    {
        $conflicts = [];

        foreach ($snapshot->rows('door_models') as $row) {
            if (DoorModel::query()->where('slug', (string) $row['slug'])->exists()) {
                $conflicts[] = sprintf('model dveří se slugem "%s"', (string) $row['slug']);
            }
        }

        $codedModels = [
            'decors' => Decor::class,
            'glass_types' => GlassType::class,
            'surcharges' => Surcharge::class,
            'door_frames' => DoorFrame::class,
            'hardware' => Hardware::class,
        ];

        foreach ($codedModels as $table => $modelClass) {
            foreach ($snapshot->rows($table) as $row) {
                if ($modelClass::query()->where('code', (string) $row['code'])->exists()) {
                    $conflicts[] = sprintf('%s s kódem "%s"', DoorCatalogSnapshot::TABLE_LABELS[$table], (string) $row['code']);
                }
            }
        }

        $modelSlugs = [];

        foreach ($snapshot->rows('door_models') as $row) {
            $modelSlugs[(int) $row['id']] = (string) $row['slug'];
        }

        foreach ($snapshot->rows('door_variants') as $row) {
            $modelSlug = $modelSlugs[(int) $row['door_model_id']] ?? null;

            if ($modelSlug === null) {
                continue;
            }

            $exists = DoorVariant::query()
                ->where('code', (string) $row['code'])
                ->whereHas('doorModel', fn ($query) => $query->where('slug', $modelSlug))
                ->exists();

            if ($exists) {
                $conflicts[] = sprintf('varianta "%s" modelu "%s"', (string) $row['code'], $modelSlug);
            }
        }

        return $conflicts;
    }

    /**
     * @return array<string, mixed>
     */
    public function import(DoorCatalogSnapshot $snapshot, bool $force): array
    {
        $conflicts = $this->detectConflicts($snapshot);

        if ($conflicts !== [] && ! $force) {
            throw DoorCatalogSnapshotException::conflicts($conflicts);
        }

        $this->reset($conflicts);

        /** @var list<Media> $createdMedia */
        $createdMedia = [];

        try {
            DB::transaction(function () use ($snapshot, &$createdMedia): void {
                $this->importDecors($snapshot);
                $this->importGlassTypes($snapshot);
                $this->importSurcharges($snapshot);
                $this->importDoorFrames($snapshot);
                $this->importHardware($snapshot);
                $this->importDoorModels($snapshot);
                $this->importDoorVariants($snapshot);
                $this->importDefaultSelections($snapshot);
                $this->importRelations($snapshot);
                $this->importMedia($snapshot, $createdMedia);
            });
        } catch (Throwable $exception) {
            $this->removeCreatedMedia($createdMedia);

            throw $exception;
        }

        return $this->report;
    }

    /**
     * @param  list<string>  $conflicts
     */
    private function reset(array $conflicts): void
    {
        $this->decorIds = [];
        $this->glassTypeIds = [];
        $this->surchargeIds = [];
        $this->doorModelIds = [];
        $this->doorVariantIds = [];
        $this->doorFrameIds = [];
        $this->hardwareIds = [];

        $tables = array_keys(DoorCatalogSnapshot::COLUMNS);

        $this->report = [
            'conflicts' => $conflicts,
            'created' => array_fill_keys($tables, 0),
            'updated' => array_fill_keys($tables, 0),
            'defaults' => 0,
            'relations_attached' => 0,
            'media_created' => 0,
            'media_skipped' => 0,
            'warnings' => [],
        ];
    }

    private function importDecors(DoorCatalogSnapshot $snapshot): void
    {
        foreach ($snapshot->rows('decors') as $row) {
            $decor = Decor::query()->where('code', (string) $row['code'])->first();
            $isNew = $decor === null;
            $decor ??= new Decor();

            $this->fillAttributes($decor, $row, DoorCatalogSnapshot::COLUMNS['decors']);
            $decor->save();

            $this->decorIds[(int) $row['id']] = (int) $decor->getKey();
            $this->report[$isNew ? 'created' : 'updated']['decors']++;
        }
    }

    private function importGlassTypes(DoorCatalogSnapshot $snapshot): void
    {
        foreach ($snapshot->rows('glass_types') as $row) {
            $glassType = GlassType::query()->where('code', (string) $row['code'])->first();
            $isNew = $glassType === null;
            $glassType ??= new GlassType();

            $this->fillAttributes($glassType, $row, DoorCatalogSnapshot::COLUMNS['glass_types']);
            $glassType->save();

            $this->glassTypeIds[(int) $row['id']] = (int) $glassType->getKey();
            $this->report[$isNew ? 'created' : 'updated']['glass_types']++;
        }
    }

    private function importSurcharges(DoorCatalogSnapshot $snapshot): void
    {
        foreach ($snapshot->rows('surcharges') as $row) {
            $surcharge = Surcharge::query()->where('code', (string) $row['code'])->first();
            $isNew = $surcharge === null;
            $surcharge ??= new Surcharge();

            $this->fillAttributes($surcharge, $row, DoorCatalogSnapshot::COLUMNS['surcharges']);
            $surcharge->save();

            $this->surchargeIds[(int) $row['id']] = (int) $surcharge->getKey();
            $this->report[$isNew ? 'created' : 'updated']['surcharges']++;
        }
    }

    private function importDoorFrames(DoorCatalogSnapshot $snapshot): void
    {
        foreach ($snapshot->rows('door_frames') as $row) {
            $doorFrame = DoorFrame::query()->where('code', (string) $row['code'])->first();
            $isNew = $doorFrame === null;
            $doorFrame ??= new DoorFrame();

            $this->fillAttributes($doorFrame, $row, DoorCatalogSnapshot::COLUMNS['door_frames']);
            $doorFrame->save();

            $this->doorFrameIds[(int) $row['id']] = (int) $doorFrame->getKey();
            $this->report[$isNew ? 'created' : 'updated']['door_frames']++;
        }
    }

    private function importHardware(DoorCatalogSnapshot $snapshot): void
    {
        foreach ($snapshot->rows('hardware') as $row) {
            $hardware = Hardware::query()->where('code', (string) $row['code'])->first();
            $isNew = $hardware === null;
            $hardware ??= new Hardware();

            $this->fillAttributes($hardware, $row, DoorCatalogSnapshot::COLUMNS['hardware']);
            $hardware->save();

            $this->hardwareIds[(int) $row['id']] = (int) $hardware->getKey();
            $this->report[$isNew ? 'created' : 'updated']['hardware']++;
        }
    }

    private function importDoorModels(DoorCatalogSnapshot $snapshot): void
    {
        // The default selections point at variants and decors that do not exist yet, so they are
        // written once every referenced row has its destination id.
        $columns = array_values(array_diff(
            DoorCatalogSnapshot::COLUMNS['door_models'],
            ['default_variant_id', 'default_decor_id', 'base_price_with_vat'],
        ));

        foreach ($snapshot->rows('door_models') as $row) {
            $doorModel = DoorModel::query()->where('slug', (string) $row['slug'])->first();
            $isNew = $doorModel === null;
            $doorModel ??= new DoorModel();

            $this->fillAttributes($doorModel, $row, $columns);

            // The model recomputes the gross price from the net price and the VAT rate of this
            // installation. The stored gross value of the snapshot is written afterwards, so the
            // destination shows exactly the price the source had.
            $doorModel->base_price_with_vat = $row['base_price_with_vat'];
            $doorModel->save();

            $this->doorModelIds[(int) $row['id']] = (int) $doorModel->getKey();
            $this->report[$isNew ? 'created' : 'updated']['door_models']++;
        }
    }

    private function importDoorVariants(DoorCatalogSnapshot $snapshot): void
    {
        // The source id of the model is never written, it is replaced by the mapped destination id.
        $columns = array_values(array_diff(DoorCatalogSnapshot::COLUMNS['door_variants'], ['door_model_id']));

        foreach ($snapshot->rows('door_variants') as $row) {
            $sourceModelId = (int) $row['door_model_id'];
            $doorModelId = $this->doorModelIds[$sourceModelId] ?? null;

            if ($doorModelId === null) {
                $this->report['warnings'][] = sprintf(
                    'Varianta "%s" byla přeskočena, její model ve snapshotu chybí.',
                    (string) $row['code'],
                );

                continue;
            }

            $variant = DoorVariant::query()
                ->where('door_model_id', $doorModelId)
                ->where('code', (string) $row['code'])
                ->first();

            $isNew = $variant === null;
            $variant ??= new DoorVariant();

            $this->fillAttributes($variant, $row, $columns);
            $variant->door_model_id = $doorModelId;
            $variant->save();

            $this->doorVariantIds[(int) $row['id']] = (int) $variant->getKey();
            $this->report[$isNew ? 'created' : 'updated']['door_variants']++;
        }
    }

    private function importDefaultSelections(DoorCatalogSnapshot $snapshot): void
    {
        foreach ($snapshot->rows('door_models') as $row) {
            $doorModelId = $this->doorModelIds[(int) $row['id']] ?? null;

            if ($doorModelId === null) {
                continue;
            }

            $variantId = $this->doorVariantIds[(int) ($row['default_variant_id'] ?? 0)] ?? null;
            $decorId = $this->decorIds[(int) ($row['default_decor_id'] ?? 0)] ?? null;

            if ($row['default_variant_id'] !== null && $variantId === null) {
                $this->report['warnings'][] = sprintf(
                    'Model "%s" odkazuje na výchozí variantu, která ve snapshotu chybí.',
                    (string) $row['slug'],
                );
            }

            if ($row['default_decor_id'] !== null && $decorId === null) {
                $this->report['warnings'][] = sprintf(
                    'Model "%s" odkazuje na výchozí dekor, který ve snapshotu chybí.',
                    (string) $row['slug'],
                );
            }

            // A plain update would stamp a new updated_at, so the snapshot value is written as well
            // and the transfer keeps the history of the source installation.
            DoorModel::query()->whereKey($doorModelId)->update([
                'default_variant_id' => $variantId,
                'default_decor_id' => $decorId,
                'updated_at' => $row['updated_at'],
            ]);

            $this->report['defaults']++;
        }
    }

    private function importRelations(DoorCatalogSnapshot $snapshot): void
    {
        $this->attachRelation($snapshot, 'decor_door_model', 'decors', 'decor_id', $this->decorIds);
        $this->attachRelation($snapshot, 'door_model_glass_type', 'glasses', 'glass_type_id', $this->glassTypeIds);
        $this->attachRelation($snapshot, 'door_model_surcharge', 'surcharges', 'surcharge_id', $this->surchargeIds);
        $this->attachRelation($snapshot, 'door_frame_door_model', 'frames', 'door_frame_id', $this->doorFrameIds);
        $this->attachRelation($snapshot, 'door_model_hardware', 'hardware', 'hardware_id', $this->hardwareIds);
    }

    /**
     * Attaches only the missing rows. `syncWithoutDetaching` never removes a relation that the
     * destination holds outside of the snapshot, which is why it is used instead of `sync`.
     *
     * @param  array<int, int>  $relatedIdsBySourceId
     */
    private function attachRelation(
        DoorCatalogSnapshot $snapshot,
        string $table,
        string $relation,
        string $foreignKey,
        array $relatedIdsBySourceId,
    ): void {
        foreach ($snapshot->rows($table) as $row) {
            $doorModelId = $this->doorModelIds[(int) $row['door_model_id']] ?? null;
            $relatedId = $relatedIdsBySourceId[(int) $row[$foreignKey]] ?? null;

            if ($doorModelId === null || $relatedId === null) {
                $this->report['warnings'][] = sprintf(
                    'Vazba z tabulky %s byla přeskočena, chybí cílový záznam.',
                    $table,
                );

                continue;
            }

            $doorModel = DoorModel::query()->find($doorModelId);

            if ($doorModel === null) {
                continue;
            }

            $before = $doorModel->{$relation}()->count();
            $doorModel->{$relation}()->syncWithoutDetaching([$relatedId]);
            $after = $doorModel->{$relation}()->count();

            $this->report['relations_attached'] += max(0, $after - $before);
        }
    }

    /**
     * @param  list<Media>  $createdMedia
     */
    private function importMedia(DoorCatalogSnapshot $snapshot, array &$createdMedia): void
    {
        $existingMedia = [];

        foreach ($snapshot->mediaEntries() as $entry) {
            $owner = $this->resolveMediaOwner($entry);

            if ($owner === null) {
                $this->report['warnings'][] = sprintf(
                    'Médium "%s" bylo přeskočeno, jeho vlastník ve snapshotu chybí.',
                    (string) $entry['file_name'],
                );

                continue;
            }

            $collectionName = (string) $entry['collection'];
            $cacheKey = $entry['owner']['model'].':'.$entry['owner_id'].':'.$collectionName;
            $existingMedia[$cacheKey] ??= $owner->getMedia($collectionName);

            $alreadyImported = $existingMedia[$cacheKey]->contains(
                fn (Media $media): bool => $media->file_name === (string) $entry['file_name'],
            );

            if ($alreadyImported) {
                // A repeated forced run must not duplicate the same file.
                $this->report['media_skipped']++;

                continue;
            }

            if ($existingMedia[$cacheKey]->isNotEmpty() && $this->isSingleFileCollection($owner, $collectionName)) {
                // Adding a file to a single file collection would delete the media that is already
                // there, so an existing but different file is left untouched and only reported.
                $this->report['warnings'][] = sprintf(
                    'Kolekce "%s" už v cíli obsahuje jiný soubor, původní médium zůstalo nedotčené.',
                    $collectionName,
                );
                $this->report['media_skipped']++;

                continue;
            }

            $media = $owner
                ->addMedia($snapshot->mediaFile((string) $entry['file']))
                ->preservingOriginal()
                ->usingName((string) $entry['name'])
                ->usingFileName((string) $entry['file_name'])
                ->setOrder($entry['order_column'] === null ? null : (int) $entry['order_column'])
                ->withManipulations((array) $entry['manipulations'])
                ->withCustomProperties((array) $entry['custom_properties'])
                ->toMediaCollection($collectionName);

            $createdMedia[] = $media;
            $existingMedia[$cacheKey]->push($media);
            $this->report['media_created']++;

            if ((int) $entry['size'] !== (int) $media->size) {
                $this->report['warnings'][] = sprintf(
                    'Velikost média "%s" v cíli neodpovídá snapshotu (%d vs. %d bajtů).',
                    (string) $entry['file_name'],
                    (int) $entry['size'],
                    (int) $media->size,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function resolveMediaOwner(array $entry): DoorModel|DoorVariant|Decor|GlassType|Hardware|null
    {
        $ownerId = (int) $entry['owner_id'];

        return match ((string) $entry['owner']['model']) {
            'door_model' => $this->findTarget(DoorModel::class, $this->doorModelIds[$ownerId] ?? null),
            'door_variant' => $this->findTarget(DoorVariant::class, $this->doorVariantIds[$ownerId] ?? null),
            'decor' => $this->findTarget(Decor::class, $this->decorIds[$ownerId] ?? null),
            'glass_type' => $this->findTarget(GlassType::class, $this->glassTypeIds[$ownerId] ?? null),
            'hardware' => $this->findTarget(Hardware::class, $this->hardwareIds[$ownerId] ?? null),
            default => null,
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel|null
     */
    private function findTarget(string $modelClass, ?int $id): ?Model
    {
        return $id === null ? null : $modelClass::query()->find($id);
    }

    /**
     * The single file flag is read from the model's own collection registration instead of being
     * duplicated here, so a change of the model cannot desynchronise the import from the model.
     */
    private function isSingleFileCollection(Model&HasMedia $owner, string $collectionName): bool
    {
        return (bool) ($owner->getMediaCollection($collectionName)?->singleFile ?? false);
    }

    /**
     * Media files are written outside of the database transaction, so a failed run has to remove the
     * files it created itself. Everything that existed before the run is left alone.
     *
     * @param  list<Media>  $createdMedia
     */
    private function removeCreatedMedia(array $createdMedia): void
    {
        foreach ($createdMedia as $media) {
            try {
                // The media library removes the files of a deleted media record, so a plain delete is
                // enough and also works when the transaction already rolled the row back.
                $media->delete();
            } catch (Throwable) {
                // Best effort only: the import already failed, so the operator is told to check the
                // media directory instead of being shown a second unrelated error.
            }
        }
    }

    /**
     * Writes only the columns the snapshot is allowed to carry, so an edited manifest cannot inject
     * another attribute. The primary key is never written: the destination assigns its own ids and
     * the source id is only used as a key of the translation maps. Timestamps travel with the
     * snapshot and stay untouched.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private function fillAttributes(Model $model, array $row, array $columns): void
    {
        $attributes = [];

        foreach ($columns as $column) {
            if ($column === $model->getKeyName()) {
                continue;
            }

            if (array_key_exists($column, $row)) {
                $attributes[$column] = $row[$column];
            }
        }

        $model->forceFill($attributes);
    }
}
