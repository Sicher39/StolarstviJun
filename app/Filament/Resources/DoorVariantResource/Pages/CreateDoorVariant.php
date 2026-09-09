<?php

namespace App\Filament\Resources\DoorVariantResource\Pages;

use App\Filament\Resources\DoorVariantResource;
use App\Models\DoorVariant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDoorVariant extends CreateRecord
{
    protected static string $resource = DoorVariantResource::class;

    /** @var array<string, string|null> */
    protected array $renderLayerUploadPaths = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->renderLayerUploadPaths = $this->extractRenderLayerUploadPaths($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncRenderLayerUploads($this->getRecord());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractRenderLayerUploadPaths(array &$data): array
    {
        $paths = [];

        foreach ($this->renderLayerCollections() as $field => $collection) {
            $paths[$collection] = $this->normalizeUploadPath($data[$field] ?? null);
            unset($data[$field]);
        }

        return $paths;
    }

    private function syncRenderLayerUploads(DoorVariant $variant): void
    {
        foreach ($this->renderLayerUploadPaths as $collection => $path) {
            if ($path === null) {
                continue;
            }

            $variant->clearMediaCollection($collection);
            $variant
                ->addMediaFromDisk($path, 'public')
                ->preservingOriginal()
                ->toMediaCollection($collection);

            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<string, string> */
    private function renderLayerCollections(): array
    {
        return [
            'frame_base_upload' => 'frame_base',
            'frame_mask_upload' => 'frame_mask',
            'door_mask_upload' => 'door_mask',
            'construction_overlay_upload' => 'construction_overlay',
            'handle_overlay_upload' => 'handle_overlay',
            'source_reference_upload' => 'source_reference',
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
