<?php

namespace App\Services\Catalog;

use App\Enums\DecorTextureOrientation;
use App\Models\Decor;
use Illuminate\Support\Str;

/**
 * Tells the renderer which way the grain runs in the uploaded texture of a decor.
 *
 * The leaf and the side jambs of the frame are drawn with the grain downwards and the head piece across, and
 * the renderer reaches both by turning the same square tile by a quarter turn. Which of the two runs is drawn
 * straight therefore depends on the source image, and the source cannot answer that itself: every upload is a
 * square-ish close-up, so the pixel size says nothing and a guess from the pixels would sooner or later turn a
 * texture that was fine.
 *
 * The direction is stated as data instead - see `config/configurator.php`: a texture runs downwards unless it
 * is listed as a horizontal one. An entry matches the file name of the uploaded texture image or the code of
 * the decor, because either of them may be the one the administrator recognises: a re-upload keeps the decor
 * code but replaces the image name, and a decor created in the administration gets a generated code while its
 * texture keeps the name it was uploaded with. Both are compared as slugs.
 *
 * No source is listed today, so every decor resolves to the downwards run - the direction of every uploaded
 * texture the catalog holds. The list stays empty on purpose: it is the single place to record a source that is
 * later uploaded with its grain across, and an unknown decor - no entry, no texture at all - is vertical either
 * way.
 */
class DecorTextureOrientationResolver
{
    /**
     * Slugs of the listed horizontal sources, read once per instance so the payload of the whole catalog is
     * resolved with a single config read.
     *
     * @var array<int, string>|null
     */
    private ?array $horizontalIdentifiers = null;

    public function forDecor(Decor $decor): DecorTextureOrientation
    {
        foreach ($this->identifiersOf($decor) as $identifier) {
            if (in_array($identifier, $this->horizontalIdentifiers(), true)) {
                return DecorTextureOrientation::Horizontal;
            }
        }

        return DecorTextureOrientation::Vertical;
    }

    /**
     * @return array<int, string>
     */
    private function identifiersOf(Decor $decor): array
    {
        $identifiers = [];

        foreach ([$decor->getFirstMedia('texture_image')?->file_name, $decor->code] as $identifier) {
            if (is_string($identifier) && $identifier !== '') {
                $identifiers[] = $this->slug($identifier);
            }
        }

        return $identifiers;
    }

    /**
     * @return array<int, string>
     */
    private function horizontalIdentifiers(): array
    {
        if ($this->horizontalIdentifiers === null) {
            $this->horizontalIdentifiers = array_map(
                fn (string $identifier): string => $this->slug($identifier),
                array_values((array) config('configurator.horizontal_decor_textures', [])),
            );
        }

        return $this->horizontalIdentifiers;
    }

    private function slug(string $identifier): string
    {
        return Str::slug(pathinfo($identifier, PATHINFO_FILENAME));
    }
}
