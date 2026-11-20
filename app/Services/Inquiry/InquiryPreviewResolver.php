<?php

namespace App\Services\Inquiry;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use Spatie\MediaLibrary\HasMedia;

/**
 * Picks the best stored image of the door that the customer configured in an inquiry.
 *
 * The configuration snapshot keeps ids, codes and prices only. Since the configurator also composes the
 * chosen door into a single PNG in the browser at submit time, the administration prefers that image:
 * it is exactly what the customer saw, the door only, with the real frame, decor, glass and overlays.
 *
 * Each configured door of a request carries its own PNG, so `resolveItem()` covers the item and
 * `resolve()` covers the whole inquiry. `resolveSelection()` describes a selection the administration
 * is editing, which has no generated image of its own yet. The candidates of the inquiry are therefore:
 *
 *  1. the generated preview stored on the inquiry itself (the one piece payload keeps it there),
 *  2. the generated preview of the first item,
 *  3. `source_reference` of the chosen variant - the picture the catalog editor uploads to check the
 *     rendered variant against,
 *  4. `preview_image` of the door model,
 *  5. `preview_image` of the decor,
 *  6. `preview_image` of the glass type.
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
     * Resolved results per inquiry item id, cached the same way.
     *
     * @var array<int, array{url: string, label: string, caption: string}|null>
     */
    private array $resolvedItems = [];

    /**
     * Resolved catalog images per edited selection, keyed by the four catalog ids.
     *
     * @var array<string, array{url: string, label: string, caption: string}|null>
     */
    private array $resolvedSelections = [];

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
     * The image of one configured door of the request. An item without a generated preview stays
     * empty on purpose: the item is shown next to its own caption, so falling back to a catalog photo
     * of another door would be misleading.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    public function resolveItem(?InquiryItem $item): ?array
    {
        if ($item === null) {
            return null;
        }

        $key = (int) $item->getKey();

        if (array_key_exists($key, $this->resolvedItems)) {
            return $this->resolvedItems[$key];
        }

        return $this->resolvedItems[$key] = $this->generatedPreview(
            $item,
            InquiryItem::PREVIEW_COLLECTION,
            'Vygenerovaný náhled položky',
            'Obrázek složený konfigurátorem při odeslání poptávky: pouze dveře bez okolí a bez osobních údajů.',
        );
    }

    /**
     * The catalog image of a selection that is being edited in the administration.
     *
     * The generated PNG of an item is composed by the browser at submit time, so an item whose
     * selection was changed in the administration has no matching illustration yet. This method
     * describes what the catalog shows for the selection the form holds right now - the reference
     * image of the variant, then the photo of the model, the decor and the glass - so the editor can
     * notice that the stored picture no longer describes the quote.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    public function resolveSelection(?int $doorModelId, ?int $doorVariantId, ?int $decorId, ?int $glassTypeId): ?array
    {
        if ($doorModelId === null && $doorVariantId === null && $decorId === null && $glassTypeId === null) {
            return null;
        }

        $key = implode(':', [
            $doorModelId ?? 0,
            $doorVariantId ?? 0,
            $decorId ?? 0,
            $glassTypeId ?? 0,
        ]);

        if (array_key_exists($key, $this->resolvedSelections)) {
            return $this->resolvedSelections[$key];
        }

        return $this->resolvedSelections[$key] = $this->catalogImage(
            $doorVariantId === null ? null : DoorVariant::query()->find($doorVariantId),
            $doorModelId === null ? null : DoorModel::query()->find($doorModelId),
            $decorId === null ? null : Decor::query()->find($decorId),
            $glassTypeId === null ? null : GlassType::query()->find($glassTypeId),
        );
    }

    /**
     * @return array{url: string, label: string, caption: string}|null
     */
    private function find(Inquiry $inquiry): ?array
    {
        $generated = $this->generatedPreview(
            $inquiry,
            Inquiry::PREVIEW_COLLECTION,
            'Vygenerovaný náhled konfigurace',
            'Obrázek složený konfigurátorem při odeslání poptávky: pouze dveře bez okolí a bez osobních údajů.',
        );

        if ($generated !== null) {
            return $generated;
        }

        $firstItemPreview = $this->firstItemPreview($inquiry);

        if ($firstItemPreview !== null) {
            return $firstItemPreview;
        }

        return $this->catalogImage(
            $inquiry->doorVariant,
            $inquiry->doorModel,
            $inquiry->decor,
            $inquiry->glassType,
        );
    }

    /**
     * The best image the catalog itself offers for a selection, in the documented order: the reference
     * image of the variant, the photo of the model, the preview of the decor and of the glass.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    private function catalogImage(
        ?DoorVariant $doorVariant,
        ?DoorModel $doorModel,
        ?Decor $decor,
        ?GlassType $glassType,
    ): ?array {
        $candidates = [
            [
                $doorVariant,
                'source_reference',
                'Referenční obrázek varianty',
                'Obrázek, podle kterého se v katalogu kontroluje vykreslení vybrané varianty. Poptávka neobsahuje vygenerovaný náhled.',
            ],
            [
                $doorModel,
                'preview_image',
                'Náhledová fotografie modelu',
                'Vybraná varianta nemá referenční obrázek, proto se zobrazuje fotografie modelu.',
            ],
            [
                $decor,
                'preview_image',
                'Náhledová fotografie dekoru',
                'Model ani varianta nemají obrázek, proto se zobrazuje náhled zvoleného dekoru.',
            ],
            [
                $glassType,
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
     * A request stored with the item payload keeps the illustration on the first item instead of the
     * inquiry, so the inquiry level image stays readable for every record.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    private function firstItemPreview(Inquiry $inquiry): ?array
    {
        $firstItem = $inquiry->items()->first();

        if (! $firstItem instanceof InquiryItem) {
            return null;
        }

        return $this->resolveItem($firstItem);
    }

    /**
     * The PNG that the configurator composed at submit time. Older inquiries have no such media, so the
     * catalog fallbacks keep every legacy record readable.
     *
     * @return array{url: string, label: string, caption: string}|null
     */
    private function generatedPreview(HasMedia $model, string $collection, string $label, string $caption): ?array
    {
        if (! $model->hasMedia($collection)) {
            return null;
        }

        $url = $model->getFirstTemporaryUrl(
            now()->addMinutes(self::GENERATED_PREVIEW_URL_LIFETIME_MINUTES),
            $collection,
        );

        if (! filled($url)) {
            return null;
        }

        return [
            'url' => $url,
            'label' => $label,
            'caption' => $caption,
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
