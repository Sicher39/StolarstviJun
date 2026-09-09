<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DoorTemplate extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'door_model_id',
        'name',
        'code',
        'config',
        'active',
    ];

    public function doorModel(): BelongsTo
    {
        return $this->belongsTo(DoorModel::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (['base_image', 'door_mask', 'glass_mask', 'overlay_image', 'preview_image'] as $collectionName) {
            $this->addMediaCollection($collectionName)
                ->useDisk('public')
                ->singleFile();
        }
    }

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'active' => 'boolean',
        ];
    }
}
