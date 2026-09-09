<?php

namespace App\Filament\Resources\DoorVariantResource\Pages;

use App\Filament\Resources\DoorVariantResource;
use App\Models\DoorVariant;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditDoorVariant extends EditRecord
{
    protected static string $resource = DoorVariantResource::class;

    /** @var array<string, string|null> */
    protected array $renderLayerUploadPaths = [];

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
        foreach ($this->renderLayerCollections() as $field => $collection) {
            $media = $this->getRecord()->getFirstMedia($collection);
            $data[$field] = $media ? $media->getKey().'/'.$media->file_name : null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->renderLayerUploadPaths = $this->extractRenderLayerUploadPaths($data);

        return $data;
    }

    protected function afterSave(): void
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
            if ($path === null || ! str_starts_with($path, 'door-variant-upload-staging/')) {
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
