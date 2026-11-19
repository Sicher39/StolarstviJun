<?php

namespace App\Services\Inquiry;

use App\Models\Inquiry;
use Spatie\MediaLibrary\HasMedia;

/**
 * Picks the best stored image of the door that the customer configured in an inquiry.
 *
 * The configuration snapshot keeps ids, codes and prices only. Since the configurator also composes the
 * chosen door into a single PNG in the browser at submit time, the administration prefers that image:
 * it is exactly what the customer saw, the door only, with the real frame, decor, glass and overlays.
 *
 * The candidates are therefore:
 *
 *  1. the generated preview stored on the inquiry itself (`Inquiry::PREVIEW_COLLECTION`),
 *  2. `source_reference` of the chosen variant - the picture the catalog editor uploads to check the
 *     rendered variant against,
 *  3. `preview_image` of the door model,
 *  4. `preview_image` of the decor,
 *  5. `preview_image` of the glass type.
 *
 * The URL is resolved from the media library on every render and is never written into the inquiry, so
 * moving the media disk or the public path can not leave a stale URL behind in the database. The generated
 * preview lives on the private disk, so it is returned as a short lived signed URL; the catalog fallbacks
 * are public images and keep their plain URL.
 */
class InquiryPreviewResolver
{
    /**
     * Long enough for a page that stays open while it is being handled, short enough to not leak the
     * uploaded file through a copied URL.
     */
    private const GENERATED_PREVIEW_URL_LIFETIME_MINUTES = 60;

    /**
     * Resolved results per inquiry id, so one rendered page queries the media library only once.
     *
     * @var array<int, array{url: string, label: string, caption: string}|null>
     */
    private array $resolved = [];

    /**
     * @return array{url: string, label: string, caption: string}|null
     */
    public function resolve(?Inquiry $inquiry): ?array
    {
        if ($inquiry === null) {
            return null;
        }

        $key = (int) $inquiry->getKey();

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        return $this->resolved[$key] = $this->find($inquiry);
    }

    /**
     * @return array{url: string, label: string, caption: string}|null
     */
    private function find(Inquiry $inquiry): ?array
    {
        $generated = $this->generatedPreview($inquiry);

        if ($generated !== null) {
            return $generated;
        }

        $candidates = [
            [
                $inquiry->doorVariant,
                'source_reference',
                'Referenční obrázek varianty',
                'Obrázek, podle kterého se v katalogu kontroluje vykreslení vybrané varianty. Poptávka neobsahuje vygenerovaný náhled.',
            ],
            [
                $inquiry->doorModel,
                'preview_image',
                'Náhledová fotografie modelu',
                'Vybraná varianta nemá referenční obrázek, proto se zobrazuje fotografie modelu.',
            ],
            [
                $inquiry->decor,
                'preview_image',
                'Náhledová fotografie dekoru',
                'Model ani varianta nemají obrázek, proto se zobrazuje náhled zvoleného dekoru.',
            ],
            [
                $inquiry->glassType,
                'preview_image',
                'Náhledová fotografie skla',
                'Model, varianta ani dekor nemají obrázek, proto se zobrazuje náhled zvoleného skla.',
            ],
        ];

        foreach ($candidates as [$model, $collection, $label, $caption]) {
            $url = $this->mediaUrl($model, $collection);

            if ($url !== null) {
                return [
                    'url' => $url,
                    'label' => $label,
                    'caption' => $caption,
                ];
            }
        }

        return null;
    }

    /**
     * The PNG that the configurator composed at submit time. Older inquiries have no such media, so the
     * catalog fallbacks below keep every legacy record readable.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    private function generatedPreview(Inquiry $inquiry): ?array
    {
        if (! $inquiry->hasMedia(Inquiry::PREVIEW_COLLECTION)) {
            return null;
        }

        $url = $inquiry->getFirstTemporaryUrl(
            now()->addMinutes(self::GENERATED_PREVIEW_URL_LIFETIME_MINUTES),
            Inquiry::PREVIEW_COLLECTION,
        );

        if (! filled($url)) {
            return null;
        }

        return [
            'url' => $url,
            'label' => 'Vygenerovaný náhled konfigurace',
            'caption' => 'Obrázek složený konfigurátorem při odeslání poptávky: pouze dveře bez okolí a bez osobních údajů.',
        ];
    }

    private function mediaUrl(mixed $model, string $collection): ?string
    {
        if (! $model instanceof HasMedia) {
            return null;
        }

        if (! $model->hasMedia($collection)) {
            return null;
        }

        $url = $model->getFirstMediaUrl($collection);

        return filled($url) ? $url : null;
    }
}
