<?php

namespace App\Services\Inquiry;

use App\Models\Decor;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Hardware;

/**
 * The catalog selection of one inquiry item, as the administration form shows it.
 *
 * An item created before the application started storing the selection in its own columns keeps the
 * four ids in the `configuration` snapshot only. The edit form needs them as field state, so they are
 * read back from the snapshot - but only when the catalog row still exists, because the columns are real
 * foreign keys and would refuse a dangling id on save.
 *
 * The snapshot itself is never changed here. It stays the record of what the customer sent, while the
 * columns carry what the workshop currently quotes.
 *
 * The frame and the hardware of the „Ostatní“ step are read the same way, with one addition: an accessory
 * is only filled when it is still active and still offered for the model of the item, because the item
 * form refuses a pair the catalog does not offer.
 */
class InquiryItemSelectionResolver
{
    /**
     * The attributes the repeater should hydrate for one item: the stored columns, completed by the
     * snapshot wherever a column is still empty.
     *
     * @param  array<string, mixed>  $fillData
     * @return array<string, mixed>
     */
    public function withSnapshotSelection(array $fillData): array
    {
        $snapshot = is_array($fillData['configuration'] ?? null) ? $fillData['configuration'] : [];

        // A stored column is a foreign key, so it is trusted as it is. Only a missing column is filled
        // from the snapshot, and then only when that catalog row still exists.
        $storedVariantId = $this->intOrNull($fillData['door_variant_id'] ?? null);

        $doorModelId = $this->intOrNull($fillData['door_model_id'] ?? null)
            ?? $this->existingSnapshotId($snapshot, 'door_model', DoorModel::class);

        $doorVariantId = $storedVariantId
            ?? $this->existingSnapshotId($snapshot, 'door_variant', DoorVariant::class);

        // A variant of a different model can not be saved into the item, so a snapshot pair that no
        // longer fits the model is dropped instead of failing the whole save on a foreign key.
        if (
            ($storedVariantId === null)
            && ($doorVariantId !== null)
            && ($doorModelId !== null)
            && ! $this->variantBelongsToModel($doorVariantId, $doorModelId)
        ) {
            $doorVariantId = null;
        }

        return [
            ...$fillData,
            'door_model_id' => $doorModelId,
            'door_variant_id' => $doorVariantId,
            'decor_id' => $this->intOrNull($fillData['decor_id'] ?? null)
                ?? $this->existingSnapshotId($snapshot, 'decor', Decor::class),
            'glass_type_id' => $this->intOrNull($fillData['glass_type_id'] ?? null)
                ?? $this->existingSnapshotId($snapshot, 'glass_type', GlassType::class),
            // The accessories of the „Ostatní“ step follow the same rule, with one addition: they also have
            // to be offered for the model, because the form refuses an accessory of another model and an
            // item filled with one could never be saved again.
            'door_frame_id' => $this->intOrNull($fillData['door_frame_id'] ?? null)
                ?? $this->snapshotAccessoryId($snapshot, 'door_frame', DoorFrame::class, $doorModelId),
            'hardware_id' => $this->intOrNull($fillData['hardware_id'] ?? null)
                ?? $this->snapshotAccessoryId($snapshot, 'hardware', Hardware::class, $doorModelId),
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function existingId(mixed $id, string $model): ?int
    {
        if (! is_numeric($id)) {
            return null;
        }

        $id = (int) $id;

        return $model::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * The id stored in one block of the snapshot, when that catalog row still exists.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function existingSnapshotId(array $snapshot, string $block, string $model): ?int
    {
        $section = $snapshot[$block] ?? null;

        if (! is_array($section)) {
            return null;
        }

        return $this->existingId($section['id'] ?? null, $model);
    }

    private function variantBelongsToModel(int $doorVariantId, ?int $doorModelId): bool
    {
        if ($doorModelId === null) {
            return DoorVariant::query()->whereKey($doorVariantId)->exists();
        }

        return DoorVariant::query()
            ->whereKey($doorVariantId)
            ->where('door_model_id', $doorModelId)
            ->exists();
    }

    /**
     * The accessory of one snapshot block, when the catalog still offers it for the model of the item.
     *
     * An id of an accessory is a foreign key, and the form refuses a pair the catalog does not offer, so
     * an accessory that was deactivated or detached from the model in the meantime is dropped here
     * instead of being hydrated into a field the item could never be saved with. Without a model the
     * compatibility can not be decided at all, so nothing is filled either.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  class-string<DoorFrame|Hardware>  $accessory
     */
    private function snapshotAccessoryId(array $snapshot, string $block, string $accessory, ?int $doorModelId): ?int
    {
        if ($doorModelId === null) {
            return null;
        }

        $section = $snapshot[$block] ?? null;

        if (! is_array($section)) {
            return null;
        }

        $id = $this->intOrNull($section['id'] ?? null);

        if ($id === null) {
            return null;
        }

        $isOffered = $accessory::query()
            ->whereKey($id)
            ->where('active', true)
            ->whereHas('doorModels', fn ($query) => $query->whereKey($doorModelId))
            ->exists();

        return $isOffered ? $id : null;
    }
}
