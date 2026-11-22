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
 * Hardware the configurator offers in the „Ostatní“ section: a handle, a fitting or another
 * accessory of the finished door.
 *
 * The picture is the only media of the row, so the visitor sees the same image the administration
 * uploaded. Only the net price is stored and the gross price is derived from the central VAT rate.
 * The door models the hardware fits are decided here, on the accessory, through the `doorModels`
 * relation.
 */
class Hardware extends Model implements HasMedia
{
    use GeneratesSystemCode;
    use HasFactory, InteractsWithMedia;

    /**
     * The word has no separate plural in the sense used here, so the table is named explicitly
     * instead of leaving it to a pluralisation rule.
     */
    protected $table = 'hardware';

    protected $fillable = [
        'name',
        'code',
        'price_without_vat',
        'sort_order',
        'active',
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
        return $this->belongsToMany(DoorModel::class, 'door_model_hardware')->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->useDisk('public')
            ->singleFile();
    }

    protected function systemCodePrefix(): string
    {
        return 'HWD';
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
        ];
    }
}
