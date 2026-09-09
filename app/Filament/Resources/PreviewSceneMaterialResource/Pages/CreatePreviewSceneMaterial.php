<?php

namespace App\Filament\Resources\PreviewSceneMaterialResource\Pages;

use App\Filament\Resources\PreviewSceneMaterialResource;
use App\Models\PreviewSceneMaterial;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreatePreviewSceneMaterial extends CreateRecord
{
    protected static string $resource = PreviewSceneMaterialResource::class;

    protected ?string $textureUploadPath = null;

    protected ?string $previewUploadPath = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->textureUploadPath = $this->normalizeUploadPath($data['texture_image_upload'] ?? null);
        $this->previewUploadPath = $this->normalizeUploadPath($data['preview_image_upload'] ?? null);
        unset($data['texture_image_upload'], $data['preview_image_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncUploads($this->getRecord());
    }

    private function syncUploads(PreviewSceneMaterial $material): void
    {
        if ($this->textureUploadPath !== null) {
            $material->clearMediaCollection('texture_image');
            $material->addMediaFromDisk($this->textureUploadPath, 'public')->preservingOriginal()->toMediaCollection('texture_image');
        }

        $previewPath = $this->previewUploadPath ?? $this->textureUploadPath;

        if ($previewPath !== null) {
            $material->clearMediaCollection('preview_image');
            $material->addMediaFromDisk($previewPath, 'public')->preservingOriginal()->toMediaCollection('preview_image');
        }

        foreach (array_filter(array_unique([$this->textureUploadPath, $previewPath])) as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
