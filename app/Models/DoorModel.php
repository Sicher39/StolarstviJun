<?php

namespace App\Models;

use App\Enums\DoorModelType;
use App\Services\VatCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DoorModel extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected static function booted(): void
    {
        static::creating(function (self $doorModel): void {
            if (filled($doorModel->slug)) {
                return;
            }

            $slug = Str::slug($doorModel->name) ?: 'model-dveri';

            $doorModel->slug = sprintf('%s-%s', $slug, Str::lower((string) Str::ulid()));
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'category',
        'type',
        'description',
        'base_price_without_vat',
        'default_variant_id',
        'default_decor_id',
        'active',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(DoorVariant::class);
    }

    public function decors(): BelongsToMany
    {
        return $this->belongsToMany(Decor::class)->withTimestamps();
    }

    /**
     * Optional preselection used by the configurator when the model is chosen.
     */
    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(DoorVariant::class, 'default_variant_id');
    }

    /**
     * Optional preselection used by the configurator when the model is chosen.
     */
    public function defaultDecor(): BelongsTo
    {
        return $this->belongsTo(Decor::class, 'default_decor_id');
    }

    public function glasses(): BelongsToMany
    {
        return $this->belongsToMany(GlassType::class, 'door_model_glass_type')->withTimestamps();
    }

    public function surcharges(): BelongsToMany
    {
        return $this->belongsToMany(Surcharge::class)->withTimestamps();
    }

    /**
     * Frame types this model may be configured with. The relation is maintained from the accessory,
     * because the same frame is offered to many models.
     */
    public function frames(): BelongsToMany
    {
        return $this->belongsToMany(DoorFrame::class, 'door_frame_door_model')->withTimestamps();
    }

    /**
     * Hardware this model may be configured with, maintained from the accessory as well.
     */
    public function hardware(): BelongsToMany
    {
        return $this->belongsToMany(Hardware::class, 'door_model_hardware')->withTimestamps();
    }

    /**
     * Catalog order of the configurator: foiled models first, veneered second, then the models whose
     * type has not been set yet, each group sorted by name. The ranks come from the enum, so the
     * order is defined once and the listing cannot drift away from the labels.
     */
    public function scopeOrderedForConfigurator(Builder $query): void
    {
        $expression = 'CASE type';
        $bindings = [];
        $lastRank = 0;

        foreach (DoorModelType::cases() as $type) {
            $expression .= ' WHEN ? THEN '.$type->rank();
            $bindings[] = $type->value;
            $lastRank = max($lastRank, $type->rank());
        }

        $query
            ->orderByRaw($expression.' ELSE '.($lastRank + 1).' END', $bindings)
            ->orderBy('name');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('preview_image')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk('public');
    }

    protected function basePriceWithoutVat(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): array => [
                'base_price_without_vat' => $value,
                'base_price_with_vat' => VatCalculator::applyRate((float) $value),
            ],
        );
    }

    protected function casts(): array
    {
        return [
            'base_price_without_vat' => 'decimal:2',
            'base_price_with_vat' => 'decimal:2',
            'type' => DoorModelType::class,
            'default_variant_id' => 'integer',
            'default_decor_id' => 'integer',
            'active' => 'boolean',
        ];
    }
}
