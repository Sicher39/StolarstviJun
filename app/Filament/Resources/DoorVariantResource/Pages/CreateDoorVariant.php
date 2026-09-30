<?php

namespace App\Filament\Resources\DoorVariantResource\Pages;

use App\Filament\Resources\DoorVariantResource;
use App\Models\DoorVariant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

        foreach ($this->normalizeUploadPaths($data['render_layers_batch_upload'] ?? null) as $path) {
            $collection = $this->collectionForBatchUpload($path);

            if ($paths[$collection] !== null) {
                throw ValidationException::withMessages([
                    'render_layers_batch_upload' => "Vrstva {$collection} je dodaná vícekrát. Použijte pro každou vrstvu právě jeden soubor.",
                ]);
            }

            $paths[$collection] = $path;
        }

        unset($data['render_layers_batch_upload']);

        foreach (['frame_base', 'frame_mask', 'door_mask', 'construction_overlay', 'handle_overlay'] as $requiredCollection) {
            if ($paths[$requiredCollection] === null) {
                throw ValidationException::withMessages([
                    'render_layers_batch_upload' => "Chybí povinná vrstva {$requiredCollection}.",
                ]);
            }
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
            'door_vertical_decor_mask_upload' => 'door_vertical_decor_mask',
            'door_horizontal_decor_mask_upload' => 'door_horizontal_decor_mask',
            'frame_vertical_decor_mask_upload' => 'frame_vertical_decor_mask',
            'frame_horizontal_decor_mask_upload' => 'frame_horizontal_decor_mask',
            'depth_overlay_upload' => 'depth_overlay',
            'construction_overlay_upload' => 'construction_overlay',
            'handle_overlay_upload' => 'handle_overlay',
            'source_reference_upload' => 'source_reference',
        ];
    }

    /** @return array<int, string> */
    private function normalizeUploadPaths(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter(
            $paths,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
    }

    private function collectionForBatchUpload(string $path): string
    {
        $storedFileName = basename($path);
        $originalFileName = str_contains($storedFileName, '--')
            ? explode('--', $storedFileName, 2)[1]
            : $storedFileName;
        $fileName = strtolower(pathinfo($originalFileName, PATHINFO_FILENAME));
        $normalizedFileName = str_replace(['-', '_'], '', $fileName);

        $collectionsByFileName = [
            'framebase' => 'frame_base',
            'framemask' => 'frame_mask',
            'doormask' => 'door_mask',
            'doorverticaldecormask' => 'door_vertical_decor_mask',
            'doorhorizontaldecormask' => 'door_horizontal_decor_mask',
            'frameverticaldecormask' => 'frame_vertical_decor_mask',
            'framehorizontaldecormask' => 'frame_horizontal_decor_mask',
            'depthoverlay' => 'depth_overlay',
            'constructionoverlay' => 'construction_overlay',
            'handleoverlay' => 'handle_overlay',
            'sourcereference' => 'source_reference',
        ];

        if (! isset($collectionsByFileName[$normalizedFileName])) {
            throw ValidationException::withMessages([
                'render_layers_batch_upload' => "Soubor {$fileName} nemá podporovaný název vrstvy.",
            ]);
        }

        return $collectionsByFileName[$normalizedFileName];
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
