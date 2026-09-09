<?php

namespace App\Filament\Resources\PreviewSceneMaterialResource\Pages;

use App\Filament\Resources\PreviewSceneMaterialResource;
use App\Models\PreviewSceneMaterial;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditPreviewSceneMaterial extends EditRecord
{
    protected static string $resource = PreviewSceneMaterialResource::class;

    protected ?string $textureUploadPath = null;

    protected ?string $previewUploadPath = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $texture = $this->getRecord()->getFirstMedia('texture_image');
        $preview = $this->getRecord()->getFirstMedia('preview_image');
        $data['texture_image_upload'] = $texture ? $texture->getKey().'/'.$texture->file_name : null;
        $data['preview_image_upload'] = $preview ? $preview->getKey().'/'.$preview->file_name : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->textureUploadPath = $this->normalizeUploadPath($data['texture_image_upload'] ?? null);
        $this->previewUploadPath = $this->normalizeUploadPath($data['preview_image_upload'] ?? null);
        unset($data['texture_image_upload'], $data['preview_image_upload']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncUploads($this->getRecord());
    }

    private function syncUploads(PreviewSceneMaterial $material): void
    {
        foreach ([
            'texture_image' => $this->textureUploadPath,
            'preview_image' => $this->previewUploadPath,
        ] as $collection => $path) {
            if ($path === null || ! str_starts_with($path, 'preview-scene-material-upload-staging/')) {
                continue;
            }

            $material->clearMediaCollection($collection);
            $material->addMediaFromDisk($path, 'public')->preservingOriginal()->toMediaCollection($collection);
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
