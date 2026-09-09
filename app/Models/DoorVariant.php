<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DoorVariant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'door_model_id',
        'code',
        'width',
        'height',
        'opening_direction',
        'opening_type',
        'has_glass',
        'sliding_possible',
        'price_modifier',
        'canvas_width',
        'canvas_height',
    ];

    public function doorModel(): BelongsTo
    {
        return $this->belongsTo(DoorModel::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (['frame_base', 'frame_mask', 'door_mask', 'construction_overlay', 'handle_overlay', 'source_reference'] as $collectionName) {
            $this->addMediaCollection($collectionName)
                ->useDisk('public')
                ->singleFile();
        }
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'has_glass' => 'boolean',
            'sliding_possible' => 'boolean',
            'price_modifier' => 'decimal:2',
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
        ];
    }
}
