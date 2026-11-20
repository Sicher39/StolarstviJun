<?php

namespace App\Models;

use App\Services\Inquiry\InquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Inquiry extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The illustration of the configured door that the configurator composed in the browser at the moment
     * the inquiry was sent. It contains the door only - no room, no wall, no floor and no customer data.
     */
    public const PREVIEW_COLLECTION = 'door_preview';

    /**
     * The preview is uploaded by an anonymous visitor, so it stays on the private disk and the
     * administration reads it through a short lived signed URL.
     */
    public const PREVIEW_DISK = 'local';

    protected $fillable = [
        'door_model_id',
        'door_variant_id',
        'decor_id',
        'glass_type_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_message',
        'price_without_vat',
        'price_with_vat',
        'configuration',
        'crm_payload',
        'status',
    ];

    /**
     * An inquiry always starts as "new", even when it is not created by the configurator: the column
     * default and this attribute come from the same InquiryStatus case.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => InquiryStatus::New->value,
    ];

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

    public function surcharges(): BelongsToMany
    {
        return $this->belongsToMany(Surcharge::class)
            ->withPivot('price_without_vat')
            ->withTimestamps();
    }

    /**
     * The separately configured doors of the request, in the order the customer built them.
     *
     * An inquiry sent before the items existed has no rows here: its single door is described by the
     * `door_model_id`, `door_variant_id`, `decor_id` and `glass_type_id` columns and by the
     * `configuration` snapshot, so every reader has to keep a fallback for the empty relation.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InquiryItem::class)
            ->orderBy('position')
            ->orderBy('id');
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
     * How many doors the request describes: the sum of the item quantities, or one for an inquiry
     * stored before the items existed (the old configurator had no quantity at all).
     */
    public function totalDoors(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return 1;
        }

        return (int) $items->sum(fn (InquiryItem $item): int => $item->quantity);
    }

    /**
     * True once `gdpr:anonymize-inquiries` replaced the personal data of the inquiry.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    protected function casts(): array
    {
        return [
            'price_without_vat' => 'decimal:2',
            'price_with_vat' => 'decimal:2',
            'configuration' => 'array',
            'crm_payload' => 'array',
            // Set by App\Services\Privacy\InquiryAnonymizer; it is not fillable on purpose, so a
            // request or a form can never clear it again.
            'anonymized_at' => 'datetime',
        ];
    }
}
