<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One configured door of an inquiry.
 *
 * The configurator used to describe a single door in the columns of `inquiries`. Those columns are
 * still filled with the first item, so every reader of the old shape keeps working, but a request may
 * now carry several separately configured doors. Each of them is one row here with its own name,
 * quantity, price per piece and line price.
 *
 * Together with the prices the row keeps the `configuration` snapshot of the selection, so a later
 * change of the catalog can not rewrite what the customer actually ordered. The snapshot is never
 * written by the administration; the catalog references live in their own columns, and the model,
 * variant, decor and glass are what the edit form of the inquiry offers as selects.
 */
class InquiryItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The PNG that the configurator composed for this item at submit time. It mirrors
     * `Inquiry::PREVIEW_COLLECTION`, because the media table is polymorphic and both owners store the
     * illustration exactly the same way (private disk, PNG only, one file per owner).
     */
    public const PREVIEW_COLLECTION = 'door_preview';

    public const PREVIEW_DISK = 'local';

    /**
     * A request is a shortlist for a quote, not an order, so both limits stay low: they keep one
     * anonymous submission from turning into a bulk upload of PNG files.
     */
    public const MAX_ITEMS_PER_INQUIRY = 20;

    public const MAX_QUANTITY_PER_ITEM = 100;

    protected $fillable = [
        'inquiry_id',
        'position',
        'name',
        'quantity',
        'door_model_id',
        'door_variant_id',
        'decor_id',
        'glass_type_id',
        'door_frame_id',
        'hardware_id',
        'unit_price_without_vat',
        'price_without_vat',
        'price_with_vat',
        'configuration',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'quantity' => 1,
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /**
     * The catalog references of the item as the administration edits them. They are nullable, because
     * an item stored before the columns existed keeps its selection in the snapshot only.
     */
    public function doorModel(): BelongsTo
    {
        return $this->belongsTo(DoorModel::class);
    }

    public function doorVariant(): BelongsTo
    {
        return $this->belongsTo(DoorVariant::class);
    }

    public function decor(): BelongsTo
    {
        return $this->belongsTo(Decor::class);
    }

    public function glassType(): BelongsTo
    {
        return $this->belongsTo(GlassType::class);
    }

    /**
     * The frame chosen for this item, added together with the „Ostatní“ section. Nullable for the same
     * reason as the references above: an older item keeps its selection in the snapshot only.
     */
    public function doorFrame(): BelongsTo
    {
        return $this->belongsTo(DoorFrame::class);
    }

    /**
     * The hardware chosen for this item, nullable for the same reason as the frame.
     */
    public function hardware(): BelongsTo
    {
        return $this->belongsTo(Hardware::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PREVIEW_COLLECTION)
            ->useDisk(self::PREVIEW_DISK)
            // The browser always sends a PNG (the server validates the bytes), so a different type can
            // only mean a tampered request and is refused by the media library as well.
            ->acceptsMimeTypes(['image/png'])
            ->singleFile();
    }

    /**
     * The `configuration` snapshot of this item, or an empty array when the row predates it.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return is_array($this->configuration) ? $this->configuration : [];
    }

    /**
     * The surcharges the item carried when the inquiry was sent.
     *
     * The pivot table `inquiry_surcharge` holds one row per surcharge of the whole request, not per
     * item, so the item keeps the ids of its own surcharges in the snapshot. The administration uses
     * them when it recalculates the price per piece from the catalog.
     *
     * @return list<int>
     */
    public function snapshotSurchargeIds(): array
    {
        $surcharges = $this->snapshot()['surcharges'] ?? null;

        if (! is_array($surcharges)) {
            return [];
        }

        $ids = [];

        foreach ($surcharges as $surcharge) {
            if (! is_array($surcharge) || ! is_numeric($surcharge['id'] ?? null)) {
                continue;
            }

            $ids[] = (int) $surcharge['id'];
        }

        return array_values(array_unique($ids));
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'door_model_id' => 'integer',
            'door_variant_id' => 'integer',
            'decor_id' => 'integer',
            'glass_type_id' => 'integer',
            'door_frame_id' => 'integer',
            'hardware_id' => 'integer',
            'unit_price_without_vat' => 'decimal:2',
            'price_without_vat' => 'decimal:2',
            'price_with_vat' => 'decimal:2',
            'configuration' => 'array',
        ];
    }
}
