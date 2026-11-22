<?php

namespace App\Services\Catalog;

use App\Models\Decor;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Hardware;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The published offer of the public catalog page.
 *
 * The page is a plain listing, not a configurator step: it prints every active model with all of its
 * variants and then the decors, the glass, the frames and the hardware the models publish. An option
 * is offered as soon as at least one active model carries it, and it travels with the list of those
 * models, so a card can name the doors the option belongs to.
 *
 * The payload is deliberately narrower than the configurator one: the listing compares names and
 * pictures only, so no render masks, prices, presets or surcharge rows travel with it.
 */
class CatalogListing
{
    /**
     * The whole page payload: the active models with their variants and the merged option lists.
     *
     * @return array{
     *     models: list<array<string, mixed>>,
     *     decors: list<array<string, mixed>>,
     *     glasses: list<array<string, mixed>>,
     *     frames: list<array<string, mixed>>,
     *     hardware: list<array<string, mixed>>
     * }
     */
    public function build(): array
    {
        $doorModels = $this->doorModels();

        return [
            'models' => $this->models($doorModels),
            // The order of an option is the order of the catalog it was first read from: the models are
            // already sorted for the listing and the relations keep the order of the administration
            // (`sort_order` of the accessories) or of their rows (decors and glass).
            'decors' => $this->mergeOptions(
                $doorModels,
                fn (DoorModel $doorModel) => $doorModel->decors,
                fn (Decor $decor): array => [
                    'id' => $decor->id,
                    'name' => $decor->name,
                    'code' => $decor->code,
                    // The texture is the decor itself, so it is preferred over an optional illustration of it.
                    'image_url' => $this->mediaUrl($decor, 'texture_image') ?? $this->mediaUrl($decor, 'preview_image'),
                ],
            ),
            'glasses' => $this->mergeOptions(
                $doorModels,
                fn (DoorModel $doorModel) => $doorModel->glasses,
                fn (GlassType $glass): array => [
                    'id' => $glass->id,
                    'name' => $glass->name,
                    'code' => $glass->code,
                    // The preview shows the pane in a room, which is what the visitor compares; the
                    // texture is the flat fallback of a glass that has no preview uploaded yet.
                    'image_url' => $this->mediaUrl($glass, 'preview_image') ?? $this->mediaUrl($glass, 'texture_image'),
                ],
            ),
            'frames' => $this->mergeOptions(
                $doorModels,
                fn (DoorModel $doorModel) => $doorModel->frames,
                fn (DoorFrame $doorFrame): array => [
                    'id' => $doorFrame->id,
                    'name' => $doorFrame->name,
                    'code' => $doorFrame->code,
                    // The picture of a frame is an optional illustration of the selector, so the switch of
                    // the catalog hides it here as well and the card keeps the plain text layout.
                    'image_url' => $doorFrame->show_image ? $this->mediaUrl($doorFrame, 'image') : null,
                ],
            ),
            'hardware' => $this->mergeOptions(
                $doorModels,
                fn (DoorModel $doorModel) => $doorModel->hardware,
                fn (Hardware $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'code' => $option->code,
                    'image_url' => $this->mediaUrl($option, 'image'),
                ],
            ),
        ];
    }

    /**
     * Active door models in the catalog order, with every relation the listing prints.
     *
     * @return Collection<int, DoorModel>
     */
    private function doorModels(): Collection
    {
        return DoorModel::query()
            ->where('active', true)
            ->with([
                // Every variant of an active model is offered, so the listing only needs the order of the rows.
                'variants' => fn ($query) => $query->orderBy('id'),
                // The pivot table has an `id` column as well, so the order of the decors is qualified.
                'decors' => fn ($query) => $query->where('active', true)->orderBy('decors.id'),
                'glasses' => fn ($query) => $query->where('active', true)->orderBy('name'),
                'frames' => fn ($query) => $query->where('door_frames.active', true)->orderBy('sort_order')->orderBy('name'),
                'hardware' => fn ($query) => $query->where('hardware.active', true)->orderBy('sort_order')->orderBy('name'),
            ])
            // The listing keeps the catalog order of the configurator: foiled models first, veneered second.
            ->orderedForConfigurator()
            ->get();
    }

    /**
     * @param  Collection<int, DoorModel>  $doorModels
     * @return list<array<string, mixed>>
     */
    private function models(Collection $doorModels): array
    {
        return $doorModels
            ->map(fn (DoorModel $doorModel): array => [
                'id' => $doorModel->id,
                'name' => $doorModel->name,
                'category' => $doorModel->category,
                'type' => $doorModel->type?->value,
                // The listing names the finish of the model, so the heading is not a bare name.
                'type_label' => $doorModel->type?->label(),
                'description' => $doorModel->description,
                'variants' => $doorModel->variants
                    ->map(fn (DoorVariant $variant): array => [
                        'id' => $variant->id,
                        // The code is the name the catalog gives the variant, so the card is titled by it.
                        'name' => $variant->code,
                        'width' => $variant->width,
                        'height' => $variant->height,
                        'opening_type' => $variant->opening_type,
                        'opening_direction' => $variant->opening_direction,
                        'has_glass' => $variant->has_glass,
                        'sliding_possible' => $variant->sliding_possible,
                        // The reference photo of the variant is the only picture of it; a variant without one
                        // is published with `null` and the card falls back to a text placeholder.
                        'image_url' => $this->mediaUrl($variant, 'source_reference'),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * One entry per distinct option, carrying the models that offer it.
     *
     * The same decor is usually offered for several models; duplicating its card for each of them would
     * turn the listing into a wall of repeated pictures, so the option is published once and the card
     * names the models instead. The models of one option keep the order of the listing.
     *
     * @param  Collection<int, DoorModel>  $doorModels
     * @param  Closure(DoorModel): iterable<int, Model>  $optionsOf
     * @param  Closure(Model): array<string, mixed>  $mapOption
     * @return list<array<string, mixed>>
     */
    private function mergeOptions(Collection $doorModels, Closure $optionsOf, Closure $mapOption): array
    {
        $merged = [];

        foreach ($doorModels as $doorModel) {
            foreach ($optionsOf($doorModel) as $option) {
                $optionId = (int) $option->getKey();

                if (! isset($merged[$optionId])) {
                    $merged[$optionId] = $mapOption($option) + [
                        'model_ids' => [],
                        'model_names' => [],
                    ];
                }

                $merged[$optionId]['model_ids'][] = (int) $doorModel->id;
                $merged[$optionId]['model_names'][] = (string) $doorModel->name;
            }
        }

        return array_values($merged);
    }

    /**
     * The public URL of the first picture of a media collection, or `null` when the model has none.
     */
    private function mediaUrl(object $model, string $collection): ?string
    {
        if (! method_exists($model, 'getFirstMediaUrl')) {
            return null;
        }

        $url = $model->getFirstMediaUrl($collection);

        return filled($url) ? $url : null;
    }
}
