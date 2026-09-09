<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PreviewScene extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const CANVAS_WIDTH = 1400;

    public const CANVAS_HEIGHT = 1100;

    public const DOOR_X = 487;

    public const DOOR_Y = 95;

    public const DOOR_WIDTH = 426;

    public const DOOR_HEIGHT = 900;

    protected $attributes = [
        'canvas_width' => self::CANVAS_WIDTH,
        'canvas_height' => self::CANVAS_HEIGHT,
        'door_x' => self::DOOR_X,
        'door_y' => self::DOOR_Y,
        'door_width' => self::DOOR_WIDTH,
        'door_height' => self::DOOR_HEIGHT,
    ];

    protected $fillable = [
        'name',
        'code',
        'canvas_width',
        'canvas_height',
        'door_x',
        'door_y',
        'door_width',
        'door_height',
        'active',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(PreviewSceneMaterial::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (['scene_reference', 'scene_base', 'scene_foreground', 'doorway_depth', 'wall_mask', 'floor_mask', 'doorway_mask', 'interior_background', 'interior_background_blurred'] as $collectionName) {
            $this->addMediaCollection($collectionName)
                ->useDisk('public')
                ->singleFile();
        }
    }

    protected function casts(): array
    {
        return [
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
            'door_x' => 'integer',
            'door_y' => 'integer',
            'door_width' => 'integer',
            'door_height' => 'integer',
            'active' => 'boolean',
        ];
    }
}
