<?php

namespace App\Http\Controllers;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\PreviewScene;
use App\Models\PreviewSceneMaterial;
use App\Models\Surcharge;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguratorController extends Controller
{
    public function __invoke(): Response
    {
        $doorModels = DoorModel::query()
            ->where('active', true)
            ->with([
                'variants' => fn ($query) => $query->orderBy('id'),
                'decors' => fn ($query) => $query->where('active', true)->orderBy('name'),
                'glasses' => fn ($query) => $query->where('active', true)->orderBy('name'),
                'surcharges' => fn ($query) => $query->where('active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (DoorModel $doorModel): array => [
                'id' => $doorModel->id,
                'name' => $doorModel->name,
                'slug' => $doorModel->slug,
                'category' => $doorModel->category,
                'description' => $doorModel->description,
                'base_price_without_vat' => (float) $doorModel->base_price_without_vat,
                'base_price_with_vat' => round((float) $doorModel->base_price_without_vat * 1.21, 2),
                'preview_image_url' => $this->mediaUrl($doorModel, 'preview_image'),
                'variants' => $doorModel->variants
                    ->map(fn (DoorVariant $variant): array => [
                        'id' => $variant->id,
                        'code' => $variant->code,
                        'width' => $variant->width,
                        'height' => $variant->height,
                        'opening_direction' => $variant->opening_direction,
                        'opening_type' => $variant->opening_type,
                        'has_glass' => $variant->has_glass,
                        'sliding_possible' => $variant->sliding_possible,
                        'price_modifier' => (float) $variant->price_modifier,
                        'canvas_width' => $variant->canvas_width,
                        'canvas_height' => $variant->canvas_height,
                        'frame_base_url' => $this->mediaUrl($variant, 'frame_base'),
                        'frame_mask_url' => $this->mediaUrl($variant, 'frame_mask'),
                        'door_mask_url' => $this->mediaUrl($variant, 'door_mask'),
                        'construction_overlay_url' => $this->mediaUrl($variant, 'construction_overlay'),
                        'handle_overlay_url' => $this->mediaUrl($variant, 'handle_overlay'),
                        'source_reference_url' => $this->mediaUrl($variant, 'source_reference'),
                    ])
                    ->values()
                    ->all(),
                'decors' => $doorModel->decors
                    ->map(fn (Decor $decor): array => [
                        'id' => $decor->id,
                        'name' => $decor->name,
                        'code' => $decor->code,
                        'price_modifier' => (float) $decor->price_modifier,
                        'texture_image_url' => $this->mediaUrl($decor, 'texture_image'),
                        'preview_image_url' => $this->mediaUrl($decor, 'preview_image'),
                    ])
                    ->values()
                    ->all(),
                'glasses' => $doorModel->glasses
                    ->map(fn (GlassType $glass): array => [
                        'id' => $glass->id,
                        'name' => $glass->name,
                        'code' => $glass->code,
                        'opacity' => $glass->opacity,
                        'price_modifier' => (float) $glass->price_modifier,
                        'texture_image_url' => $this->mediaUrl($glass, 'texture_image'),
                        'preview_image_url' => $this->mediaUrl($glass, 'preview_image'),
                    ])
                    ->values()
                    ->all(),
                'surcharges' => $doorModel->surcharges
                    ->map(fn (Surcharge $surcharge): array => [
                        'id' => $surcharge->id,
                        'name' => $surcharge->name,
                        'code' => $surcharge->code,
                        'price_without_vat' => (float) $surcharge->price_without_vat,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $previewScene = PreviewScene::query()
            ->where('active', true)
            ->with([
                'materials' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('type')
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('id')
            ->first();

        return Inertia::render('Configurator', [
            'doorModels' => $doorModels,
            'previewScene' => $previewScene ? [
                'id' => $previewScene->id,
                'name' => $previewScene->name,
                'code' => $previewScene->code,
                'canvas_width' => PreviewScene::CANVAS_WIDTH,
                'canvas_height' => PreviewScene::CANVAS_HEIGHT,
                'door_x' => PreviewScene::DOOR_X,
                'door_y' => PreviewScene::DOOR_Y,
                'door_width' => PreviewScene::DOOR_WIDTH,
                'door_height' => PreviewScene::DOOR_HEIGHT,
                'scene_base_url' => $this->mediaUrl($previewScene, 'scene_base'),
                'scene_foreground_url' => $this->mediaUrl($previewScene, 'scene_foreground'),
                'doorway_depth_url' => $this->mediaUrl($previewScene, 'doorway_depth'),
                'wall_mask_url' => $this->mediaUrl($previewScene, 'wall_mask'),
                'floor_mask_url' => $this->mediaUrl($previewScene, 'floor_mask'),
                'doorway_mask_url' => $this->mediaUrl($previewScene, 'doorway_mask'),
                'interior_background_url' => $this->mediaUrl($previewScene, 'interior_background'),
                'interior_background_blurred_url' => $this->mediaUrl($previewScene, 'interior_background_blurred'),
                'materials' => $previewScene->materials
                    ->map(fn (PreviewSceneMaterial $material): array => [
                        'id' => $material->id,
                        'type' => $material->type,
                        'name' => $material->name,
                        'code' => $material->code,
                        'color' => $material->color,
                        'texture_image_url' => $this->mediaUrl($material, 'texture_image'),
                        'preview_image_url' => $this->mediaUrl($material, 'preview_image'),
                    ])
                    ->values()
                    ->all(),
            ] : null,
        ]);
    }

    private function mediaUrl(object $model, string $collection): ?string
    {
        if (! method_exists($model, 'getFirstMediaUrl')) {
            return null;
        }

        $url = $model->getFirstMediaUrl($collection);

        return filled($url) ? $url : null;
    }
}
