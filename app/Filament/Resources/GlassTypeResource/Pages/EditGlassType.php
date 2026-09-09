<?php

namespace App\Filament\Resources\GlassTypeResource\Pages;

use App\Filament\Resources\GlassTypeResource;
use App\Models\GlassType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditGlassType extends EditRecord
{
    protected static string $resource = GlassTypeResource::class;

    protected ?string $textureImageUploadPath = null;

    protected ?string $previewImageUploadPath = null;

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
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->textureImageUploadPath = $this->normalizeUploadPath($data['texture_image_upload'] ?? null);
        $this->previewImageUploadPath = $this->normalizeUploadPath($data['preview_image_upload'] ?? null);

        unset($data['texture_image_upload'], $data['preview_image_upload']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncMediaUploads($this->getRecord());
    }

    private function syncMediaUploads(GlassType $glassType): void
    {
        $texturePath = $this->textureImageUploadPath;
        $previewPath = $this->previewImageUploadPath;

        if ($texturePath !== null) {
            $glassType->clearMediaCollection('texture_image');
            $glassType
                ->addMediaFromDisk($texturePath, 'public')
                ->preservingOriginal()
                ->toMediaCollection('texture_image');
        }

        if ($previewPath !== null) {
            $glassType->clearMediaCollection('preview_image');
            $glassType
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
