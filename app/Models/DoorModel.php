<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DoorModel extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'base_price_without_vat',
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

    public function glasses(): BelongsToMany
    {
        return $this->belongsToMany(GlassType::class, 'door_model_glass_type')->withTimestamps();
    }

    public function surcharges(): BelongsToMany
    {
        return $this->belongsToMany(Surcharge::class)->withTimestamps();
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
                'base_price_with_vat' => round((float) $value * 1.21, 2),
            ],
        );
    }

    protected function casts(): array
    {
        return [
            'base_price_without_vat' => 'decimal:2',
            'base_price_with_vat' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
