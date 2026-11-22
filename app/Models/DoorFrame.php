<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSystemCode;
use App\Services\VatCalculator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One frame type the configurator offers in the „Ostatní“ section: bez zárubně, bezfalcová,
 * obložková or obrácená.
 *
 * The frame is an accessory priced per door, so only the net price is stored and the gross price is
 * derived from the central VAT rate whenever it is read. The door models that may use it are decided
 * here, on the accessory, through the `doorModels` relation.
 *
 * The picture is an optional illustration of the selector card: it is uploaded into a single media
 * collection, and the `show_image` flag lets the catalog hide it from the public configurator while
 * the frame itself stays offered. The picture is never a layer of the door preview.
 */
class DoorFrame extends Model implements HasMedia
{
    use GeneratesSystemCode;
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'code',
        'price_without_vat',
        'sort_order',
        'active',
        'show_image',
    ];

    /**
     * The gross price of this installation, never stored: a catalog row cannot keep a gross price
     * that the configured VAT rate no longer produces.
     */
    protected $appends = [
        'price_with_vat',
    ];

    public function doorModels(): BelongsToMany
    {
        return $this->belongsToMany(DoorModel::class, 'door_frame_door_model')->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->useDisk('public')
            ->singleFile();
    }

    protected function systemCodePrefix(): string
    {
        return 'FRM';
    }

    protected function priceWithVat(): Attribute
    {
        return Attribute::make(
            get: fn (): float => VatCalculator::applyRate((float) $this->price_without_vat),
        );
    }

    protected function casts(): array
    {
        return [
            'price_without_vat' => 'decimal:2',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'show_image' => 'boolean',
        ];
    }
}
