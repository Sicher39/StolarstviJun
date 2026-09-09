<?php

namespace App\Filament\Resources\PreviewSceneResource\Pages;

use App\Filament\Resources\PreviewSceneResource;
use App\Models\PreviewScene;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreatePreviewScene extends CreateRecord
{
    protected static string $resource = PreviewSceneResource::class;

    /** @var array<string, string|null> */
    protected array $uploadPaths = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->uploadPaths = $this->extractUploads($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncUploads($this->getRecord());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function extractUploads(array &$data): array
    {
        $uploads = [];

        foreach ($this->collections() as $field => $collection) {
            $uploads[$collection] = $this->normalizeUploadPath($data[$field] ?? null);
            unset($data[$field]);
        }

        return $uploads;
    }

    private function syncUploads(PreviewScene $scene): void
    {
        foreach ($this->uploadPaths as $collection => $path) {
            if ($path === null) {
                continue;
            }

            $scene->clearMediaCollection($collection);
            $scene->addMediaFromDisk($path, 'public')->preservingOriginal()->toMediaCollection($collection);
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<string, string> */
    private function collections(): array
    {
        return [
            'scene_reference_upload' => 'scene_reference',
            'scene_base_upload' => 'scene_base',
            'scene_foreground_upload' => 'scene_foreground',
            'doorway_depth_upload' => 'doorway_depth',
            'wall_mask_upload' => 'wall_mask',
            'floor_mask_upload' => 'floor_mask',
            'doorway_mask_upload' => 'doorway_mask',
            'interior_background_upload' => 'interior_background',
            'interior_background_blurred_upload' => 'interior_background_blurred',
        ];
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
