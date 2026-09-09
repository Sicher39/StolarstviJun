<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Decor extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'code',
        'price_modifier',
        'active',
    ];

    public function doorModels(): BelongsToMany
    {
        return $this->belongsToMany(DoorModel::class)->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('texture_image')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('preview_image')
            ->useDisk('public')
            ->singleFile();
    }

    protected function casts(): array
    {
        return [
            'price_modifier' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
