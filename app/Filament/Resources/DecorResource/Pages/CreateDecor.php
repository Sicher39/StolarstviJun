<?php

namespace App\Filament\Resources\DecorResource\Pages;

use App\Filament\Resources\DecorResource;
use App\Models\Decor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDecor extends CreateRecord
{
    protected static string $resource = DecorResource::class;

    protected ?string $textureImageUploadPath = null;

    protected ?string $previewImageUploadPath = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->textureImageUploadPath = $this->normalizeUploadPath($data['texture_image_upload'] ?? null);
        $this->previewImageUploadPath = $this->normalizeUploadPath($data['preview_image_upload'] ?? null);

        unset($data['texture_image_upload'], $data['preview_image_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncMediaUploads($this->getRecord());
    }

    private function syncMediaUploads(Decor $decor): void
    {
        $texturePath = $this->textureImageUploadPath;
        $previewPath = $this->previewImageUploadPath ?? $texturePath;

        if ($texturePath !== null) {
            $decor->clearMediaCollection('texture_image');
            $decor
                ->addMediaFromDisk($texturePath, 'public')
                ->preservingOriginal()
                ->toMediaCollection('texture_image');
        }

        if ($previewPath !== null) {
            $decor->clearMediaCollection('preview_image');
            $decor
                ->addMediaFromDisk($previewPath, 'public')
                ->preservingOriginal()
                ->toMediaCollection('preview_image');
        }

        $this->deleteStagedUpload($texturePath, $previewPath);
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function deleteStagedUpload(?string ...$paths): void
    {
        foreach (array_filter(array_unique($paths)) as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
