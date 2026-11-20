<?php

namespace Database\Seeders;

use App\Models\Decor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class DecorSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function run(): void
    {
        $decorPath = public_path('seeders/dekory');

        foreach ($this->decorFiles($decorPath) as $file) {
            $slug = Str::of($file->getFilenameWithoutExtension())->slug()->toString();

            $decor = Decor::query()->updateOrCreate(
                ['code' => $slug],
                [
                    'name' => $this->makeLabelFromSlug($slug),
                    'price_modifier' => 0,
                    'active' => true,
                ],
            );

            $this->replaceMedia($decor, 'texture_image', $file->getPathname());
            $this->replaceMedia($decor, 'preview_image', $file->getPathname());
        }
    }

    /**
     * @return Collection<int, SplFileInfo>
     */
    private function decorFiles(string $decorPath): Collection
    {
        if (! File::isDirectory($decorPath)) {
            throw new \RuntimeException("Adresář s dekory neexistuje: {$decorPath}");
        }

        $files = collect(File::files($decorPath))
            ->filter(fn (SplFileInfo $file): bool => in_array(
                strtolower($file->getExtension()),
                self::SUPPORTED_EXTENSIONS,
                true,
            ))
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
            ->values();

        if ($files->isEmpty()) {
            throw new \RuntimeException("V adresáři s dekory není žádný podporovaný obrázek (jpg, jpeg, png, webp): {$decorPath}");
        }

        return $files;
    }

    private function makeLabelFromSlug(string $slug): string
    {
        return (string) Str::of($slug)
            ->replace('-', ' ')
            ->title();
    }

    /**
     * Replaces the media only in the given collection of the given decor.
     */
    private function replaceMedia(Decor $decor, string $collection, string $path): void
    {
        $decor->clearMediaCollection($collection);
        $decor
            ->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection($collection);
    }
}
