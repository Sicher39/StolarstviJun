<?php

namespace App\Filament\Resources\HardwareResource\Pages;

use App\Filament\Resources\HardwareResource;
use App\Models\Hardware;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditHardware extends EditRecord
{
    protected static string $resource = HardwareResource::class;

    protected ?string $imageUploadPath = null;

    protected bool $imageShouldBeCleared = false;

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
        $data['image_upload'] = $this->mediaState();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->imageUploadPath = $this->stagedUploadPath($data['image_upload'] ?? null);
        $this->imageShouldBeCleared = $this->isClearedUpload($data, 'image_upload');

        unset($data['image_upload']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncImageUpload($this->getRecord());
        $this->fillForm();
    }

    private function syncImageUpload(Hardware $hardware): void
    {
        $path = $this->imageUploadPath;

        if ($path !== null) {
            $hardware->clearMediaCollection('image');
            $hardware
                ->addMediaFromDisk($path, 'public')
                ->preservingOriginal()
                ->toMediaCollection('image');

            Storage::disk('public')->delete($path);

            return;
        }

        if ($this->imageShouldBeCleared) {
            $hardware->clearMediaCollection('image');
        }
    }

    /**
     * The form field is filled with the path of the stored media file, so an untouched field is
     * submitted with that path. A submitted blank value therefore means the user explicitly removed
     * the file. A missing key means the field was not part of the submitted state and the media
     * collection must be left untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function isClearedUpload(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data)) {
            return false;
        }

        if ($this->normalizeUploadPath($data[$key]) !== null) {
            return false;
        }

        return $this->getRecord()->getFirstMedia('image') !== null;
    }

    private function mediaState(): ?string
    {
        $media = $this->getRecord()->getFirstMedia('image');

        return $media ? $media->getKey().'/'.$media->file_name : null;
    }

    private function stagedUploadPath(mixed $value): ?string
    {
        $path = $this->normalizeUploadPath($value);

        return $path !== null && str_starts_with($path, 'hardware-upload-staging/') ? $path : null;
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
