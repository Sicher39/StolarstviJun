<?php

namespace Database\Seeders;

use App\Models\PreviewScene;
use App\Models\PreviewSceneMaterial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PreviewSceneSeeder extends Seeder
{
    private string $scenePath;

    public function run(): void
    {
        $this->scenePath = public_path('img/room/modern-light-room');

        if (! File::isDirectory($this->scenePath)) {
            throw new \RuntimeException("Adresář prostředí neexistuje: {$this->scenePath}");
        }

        $this->ensureRequiredAssetsExist();

        $scene = PreviewScene::query()->updateOrCreate(
            ['code' => 'modern-light-room'],
            [
                'name' => 'Široký moderní pokoj',
                'canvas_width' => PreviewScene::CANVAS_WIDTH,
                'canvas_height' => PreviewScene::CANVAS_HEIGHT,
                'door_x' => PreviewScene::DOOR_X,
                'door_y' => PreviewScene::DOOR_Y,
                'door_width' => PreviewScene::DOOR_WIDTH,
                'door_height' => PreviewScene::DOOR_HEIGHT,
                'active' => true,
            ],
        );

        $this->replaceMedia($scene, 'scene_reference', 'scene-reference.png');
        $this->replaceMedia($scene, 'scene_base', 'scene-lighting-overlay.png');
        $this->replaceMedia($scene, 'scene_foreground', 'scene-foreground-overlay.png');
        $this->replaceMedia($scene, 'doorway_depth', 'doorway-depth-overlay.png');
        $this->replaceMedia($scene, 'wall_mask', 'wall-mask.svg');
        $this->replaceMedia($scene, 'floor_mask', 'floor-mask.svg');
        $this->replaceMedia($scene, 'doorway_mask', 'doorway-mask.svg');
        $this->replaceMedia($scene, 'interior_background', 'room-behind-door.png');
        $this->replaceMedia($scene, 'interior_background_blurred', 'room-behind-door-blurred.png');

        foreach ($this->materials() as $material) {
            $record = PreviewSceneMaterial::query()->updateOrCreate(
                [
                    'preview_scene_id' => $scene->id,
                    'code' => $material['code'],
                ],
                [
                    'type' => $material['type'],
                    'name' => $material['name'],
                    'color' => $material['color'],
                    'sort_order' => $material['sort_order'],
                    'active' => true,
                ],
            );

            $this->replaceMedia($record, 'texture_image', $material['texture']);
            $this->replaceMedia($record, 'preview_image', $material['preview']);
        }
    }

    /**
     * @return array<int, array{type: string, name: string, code: string, color: ?string, sort_order: int, texture: string, preview: string}>
     */
    private function materials(): array
    {
        return [
            ['type' => 'wall', 'name' => 'Teplá bílá', 'code' => 'warm-white', 'color' => '#F2EEE7', 'sort_order' => 10, 'texture' => 'walls/warm-white.png', 'preview' => 'walls/warm-white-preview.png'],
            ['type' => 'wall', 'name' => 'Světle šedá', 'code' => 'light-grey', 'color' => '#D7D4CE', 'sort_order' => 20, 'texture' => 'walls/light-grey.png', 'preview' => 'walls/light-grey-preview.png'],
            ['type' => 'wall', 'name' => 'Antracit', 'code' => 'anthracite', 'color' => '#303238', 'sort_order' => 30, 'texture' => 'walls/anthracite.png', 'preview' => 'walls/anthracite-preview.png'],
            ['type' => 'wall', 'name' => 'Písková', 'code' => 'sand', 'color' => '#CDBA9D', 'sort_order' => 40, 'texture' => 'walls/sand.png', 'preview' => 'walls/sand-preview.png'],
            ['type' => 'wall', 'name' => 'Jemný beton', 'code' => 'soft-concrete', 'color' => '#AAA8A2', 'sort_order' => 50, 'texture' => 'walls/soft-concrete.png', 'preview' => 'walls/soft-concrete-preview.png'],
            ['type' => 'floor', 'name' => 'Světlý dub', 'code' => 'light-oak', 'color' => null, 'sort_order' => 10, 'texture' => 'floors/light-oak.png', 'preview' => 'floors/light-oak-preview.png'],
            ['type' => 'floor', 'name' => 'Přírodní dub', 'code' => 'natural-oak', 'color' => null, 'sort_order' => 20, 'texture' => 'floors/natural-oak.png', 'preview' => 'floors/natural-oak-preview.png'],
            ['type' => 'floor', 'name' => 'Tmavý dub', 'code' => 'dark-oak', 'color' => null, 'sort_order' => 30, 'texture' => 'floors/dark-oak.png', 'preview' => 'floors/dark-oak-preview.png'],
            ['type' => 'floor', 'name' => 'Světlý beton', 'code' => 'light-concrete', 'color' => null, 'sort_order' => 40, 'texture' => 'floors/light-concrete.png', 'preview' => 'floors/light-concrete-preview.png'],
        ];
    }

    private function ensureRequiredAssetsExist(): void
    {
        $requiredFiles = [
            'scene-reference.png',
            'scene-lighting-overlay.png',
            'scene-foreground-overlay.png',
            'doorway-depth-overlay.png',
            'wall-mask.svg',
            'floor-mask.svg',
            'doorway-mask.svg',
            'room-behind-door.png',
            'room-behind-door-blurred.png',
        ];

        foreach ($this->materials() as $material) {
            $requiredFiles[] = $material['texture'];
            $requiredFiles[] = $material['preview'];
        }

        $missingFiles = array_values(array_filter(
            $requiredFiles,
            fn (string $relativePath): bool => ! File::exists($this->scenePath.'/'.$relativePath),
        ));

        if ($missingFiles !== []) {
            throw new \RuntimeException('V balíčku prostředí chybí soubory: '.implode(', ', $missingFiles));
        }
    }

    private function replaceMedia(object $model, string $collection, string $relativePath): void
    {
        $path = $this->scenePath.'/'.$relativePath;

        $model->clearMediaCollection($collection);
        $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
    }
}
