<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PreviewSceneMaterial extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'preview_scene_id',
        'type',
        'name',
        'code',
        'color',
        'sort_order',
        'active',
    ];

    public function previewScene(): BelongsTo
    {
        return $this->belongsTo(PreviewScene::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (['texture_image', 'preview_image'] as $collectionName) {
            $this->addMediaCollection($collectionName)
                ->useDisk('public')
                ->singleFile();
        }
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
