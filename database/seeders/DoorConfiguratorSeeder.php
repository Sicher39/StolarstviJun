<?php

namespace Database\Seeders;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Surcharge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class DoorConfiguratorSeeder extends Seeder
{
    private string $decorPath;

    private string $glassPath;

    private string $doorModelPath;

    public function run(): void
    {
        $this->decorPath = public_path('seeders/dekory');
        $this->glassPath = public_path('seeders/dkp_glass_pack_with_previews');
        $this->doorModelPath = public_path('seeders/doors/standard');

        DB::transaction(function (): void {
            $this->resetCatalog();

            $decors = $this->seedDecors();
            $doorModel = $this->seedStandardDoorModel();
            $glassTypes = $this->seedGlassTypes();

            $this->seedStandardVariants($doorModel);

            $doorModel->decors()->sync($decors->pluck('id')->all());
            $doorModel->glasses()->sync($glassTypes->pluck('id')->all());
            $doorModel->surcharges()->sync([]);
        });
    }

    private function resetCatalog(): void
    {
        DoorModel::query()->get()->each(function (DoorModel $doorModel): void {
            $this->clearAllMediaCollections($doorModel, ['preview_image', 'gallery']);
        });

        Decor::query()->get()->each(function (Decor $decor): void {
            $this->clearAllMediaCollections($decor, ['texture_image', 'preview_image']);
        });

        GlassType::query()->get()->each(function (GlassType $glassType): void {
            $this->clearAllMediaCollections($glassType, ['texture_image', 'preview_image']);
        });

        DB::table('decor_door_model')->truncate();
        DB::table('door_model_glass_type')->truncate();
        DB::table('door_model_surcharge')->truncate();

        DoorVariant::query()->delete();
        DoorModel::query()->delete();
        Decor::query()->delete();
        GlassType::query()->delete();
        Surcharge::query()->delete();
    }

    /**
     * @return Collection<int, Decor>
     */
    private function seedDecors()
    {
        return collect(File::files($this->decorPath))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->sortBy(fn ($file): string => $file->getFilenameWithoutExtension())
            ->values()
            ->map(function ($file): Decor {
                $slug = Str::of($file->getFilenameWithoutExtension())->slug()->toString();

                $decor = Decor::query()->create([
                    'name' => $this->makeLabelFromSlug($slug),
                    'code' => $slug,
                    'price_modifier' => 0,
                    'active' => true,
                ]);

                $this->replaceSingleMedia($decor, 'texture_image', $file->getPathname());
                $this->replaceSingleMedia($decor, 'preview_image', $file->getPathname());

                return $decor;
            });
    }

    private function seedStandardDoorModel(): DoorModel
    {
        $doorModel = DoorModel::query()->create([
            'name' => 'Standard',
            'slug' => 'standard',
            'category' => 'Interiérové dveře',
            'description' => 'Aktuální seed katalogu konfigurátoru pro model Standard.',
            'base_price_without_vat' => 0,
            'base_price_with_vat' => 0,
            'active' => true,
        ]);

        return $doorModel;
    }

    /**
     * @return Collection<int, GlassType>
     */
    private function seedGlassTypes(): Collection
    {
        if (! File::isDirectory($this->glassPath)) {
            return collect([
                GlassType::query()->create([
                    'name' => 'Matné sklo',
                    'code' => 'mat-dre',
                    'opacity' => 65,
                    'price_modifier' => 0,
                    'active' => true,
                ]),
            ]);
        }

        return collect(File::directories($this->glassPath))
            ->sort()
            ->values()
            ->map(function (string $directory): GlassType {
                $meta = $this->readJsonFile($directory.'/meta.json');
                $slug = Str::of($meta['slug'] ?? basename($directory))->slug()->toString();
                $opacity = $this->normalizeOpacity($meta['opacity'] ?? 1);

                $glassType = GlassType::query()->create([
                    'name' => is_string($meta['name'] ?? null) ? $meta['name'] : $this->makeLabelFromSlug($slug),
                    'code' => $slug,
                    'opacity' => $opacity,
                    'price_modifier' => 0,
                    'active' => true,
                ]);

                $this->replaceSingleMedia($glassType, 'texture_image', $this->firstExistingPath($directory, [
                    'texture-img.webp',
                    'texture-img.png',
                    'glass.webp',
                    'glass.png',
                ]));

                $this->replaceSingleMedia($glassType, 'preview_image', $this->firstExistingPath($directory, [
                    'preview-image.webp',
                    'preview-image.png',
                    'preview-with-plant.webp',
                    'preview-with-plant.png',
                ]));

                return $glassType;
            });
    }

    private function seedStandardVariantsAndTemplates(DoorModel $doorModel): void
    {
        $variantDirectories = collect(File::directories($this->doorModelPath))
            ->filter(function (string $directory): bool {
                return File::exists($directory.'/config.json') && File::exists($directory.'/base.png');
            })
            ->sort()
            ->values();

        $firstPreviewPath = null;

        foreach ($variantDirectories as $directory) {
            $variantSlug = basename($directory);
            $hasGlass = $this->firstExistingPath($directory, ['glass-mask.svg', 'glass-mask.png']) !== null;
            $previewPath = $this->firstExistingPath($directory, ['preview.webp', 'source.png']);

            $variant = DoorVariant::query()->create([
                'door_model_id' => $doorModel->id,
                'code' => $variantSlug,
                'width' => 800,
                'height' => 1970,
                'opening_direction' => 'levé / pravé',
                'opening_type' => 'falcové / bezfalcové',
                'has_glass' => $hasGlass,
                'sliding_possible' => false,
                'price_modifier' => 0,
                'canvas_width' => 426,
                'canvas_height' => 900,
            ]);

            $this->replaceSingleMedia($variant, 'source_image', $this->firstExistingPath($directory, ['source.png', 'base.png']));
            $this->replaceSingleMedia($variant, 'frame_mask', $this->firstExistingPath($directory, ['frame-mask.png', 'frame-mask.svg']));
            $this->replaceSingleMedia($variant, 'door_mask', $this->firstExistingPath($directory, ['door-mask.png', 'door-mask.svg']));
            $this->replaceSingleMedia($variant, 'glass_mask', $this->firstExistingPath($directory, ['glass-mask.png', 'glass-mask.svg']));
            $this->replaceSingleMedia($variant, 'handle_mask', $this->firstExistingPath($directory, ['handle-mask.png', 'handle-mask.svg']));
            $this->replaceSingleMedia($variant, 'overlay_image', $this->firstExistingPath($directory, ['preview-overlay.png', 'overlay.png']));

            if ($firstPreviewPath === null && $previewPath !== null) {
                $firstPreviewPath = $previewPath;
            }
        }

        if ($firstPreviewPath !== null) {
            $this->replaceSingleMedia($doorModel, 'preview_image', $firstPreviewPath);
        }
    }

    /**
     * @param  array<int, string>  $candidateFiles
     */
    private function firstExistingPath(string $directory, array $candidateFiles): ?string
    {
        foreach ($candidateFiles as $candidateFile) {
            $path = $directory.'/'.$candidateFile;

            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeOpacity(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 100;
        }

        $opacity = (float) $value;

        if ($opacity <= 1) {
            $opacity *= 100;
        }

        return (int) min(max(round($opacity), 0), 100);
    }

    private function makeLabelFromSlug(string $slug): string
    {
        return (string) Str::of($slug)
            ->replace('-', ' ')
            ->title();
    }

    /**
     * @param  array<int, string>  $collections
     */
    private function clearAllMediaCollections(HasMedia $model, array $collections): void
    {
        foreach ($collections as $collection) {
            $model->clearMediaCollection($collection);
        }
    }

    private function replaceSingleMedia(HasMedia $model, string $collection, ?string $path): void
    {
        if ($path === null || ! File::exists($path)) {
            return;
        }

        $model->clearMediaCollection($collection);
        $model
            ->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection($collection);
    }
}
