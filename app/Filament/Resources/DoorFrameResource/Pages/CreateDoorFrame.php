<?php

namespace App\Filament\Resources\DoorFrameResource\Pages;

use App\Filament\Resources\DoorFrameResource;
use App\Models\DoorFrame;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDoorFrame extends CreateRecord
{
    protected static string $resource = DoorFrameResource::class;

    protected ?string $imageUploadPath = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->imageUploadPath = $this->normalizeUploadPath($data['image_upload'] ?? null);

        unset($data['image_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncImageUpload($this->getRecord());
    }

    private function syncImageUpload(DoorFrame $doorFrame): void
    {
        $path = $this->imageUploadPath;

        if ($path === null) {
            return;
        }

        $doorFrame->clearMediaCollection('image');
        $doorFrame
            ->addMediaFromDisk($path, 'public')
            ->preservingOriginal()
            ->toMediaCollection('image');

        Storage::disk('public')->delete($path);
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
