<?php

namespace App\Filament\Resources\HardwareResource\Pages;

use App\Filament\Resources\HardwareResource;
use App\Models\Hardware;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateHardware extends CreateRecord
{
    protected static string $resource = HardwareResource::class;

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

    private function syncImageUpload(Hardware $hardware): void
    {
        $path = $this->imageUploadPath;

        if ($path === null) {
            return;
        }

        $hardware->clearMediaCollection('image');
        $hardware
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
