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

    protected bool $textureImageShouldBeCleared = false;

    protected ?string $previewImageUploadPath = null;

    protected bool $previewImageShouldBeCleared = false;

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
        $data['texture_image_upload'] = $this->mediaState('texture_image');
        $data['preview_image_upload'] = $this->mediaState('preview_image');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->textureImageUploadPath = $this->stagedUploadPath($data['texture_image_upload'] ?? null);
        $this->previewImageUploadPath = $this->stagedUploadPath($data['preview_image_upload'] ?? null);

        $this->textureImageShouldBeCleared = $this->isClearedUpload($data, 'texture_image_upload', 'texture_image');
        $this->previewImageShouldBeCleared = $this->isClearedUpload($data, 'preview_image_upload', 'preview_image');

        unset($data['texture_image_upload'], $data['preview_image_upload']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncMediaUploads($this->getRecord());
        $this->fillForm();
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
        } elseif ($this->textureImageShouldBeCleared) {
            $glassType->clearMediaCollection('texture_image');
        }

        if ($previewPath !== null) {
            $glassType->clearMediaCollection('preview_image');
            $glassType
                ->addMediaFromDisk($previewPath, 'public')
                ->preservingOriginal()
                ->toMediaCollection('preview_image');
        } elseif ($this->previewImageShouldBeCleared) {
            $glassType->clearMediaCollection('preview_image');
        }

        $this->deleteStagedUpload($texturePath, $previewPath);
    }

    /**
     * The form field is filled with the path of the stored media file, so an untouched
     * field is submitted with that path. A submitted blank value therefore means the
     * user explicitly removed the file. A missing key means the field was not part of
     * the submitted state and the media collection must be left untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function isClearedUpload(array $data, string $key, string $collection): bool
    {
        if (! array_key_exists($key, $data)) {
            return false;
        }

        if ($this->normalizeUploadPath($data[$key]) !== null) {
            return false;
        }

        return $this->getRecord()->getFirstMedia($collection) !== null;
    }

    private function mediaState(string $collection): ?string
    {
        $media = $this->getRecord()->getFirstMedia($collection);

        return $media ? $media->getKey().'/'.$media->file_name : null;
    }

    private function stagedUploadPath(mixed $value): ?string
    {
        $path = $this->normalizeUploadPath($value);

        return $path !== null && str_starts_with($path, 'glass-upload-staging/') ? $path : null;
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
