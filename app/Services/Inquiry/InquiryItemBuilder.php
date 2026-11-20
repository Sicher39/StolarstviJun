<?php

namespace App\Services\Inquiry;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\InquiryItem;
use App\Models\Surcharge;
use App\Services\VatCalculator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves one submitted selection into a normalised inquiry item.
 *
 * The browser sends ids only. Everything that ends up in the quote - the names, the price per piece,
 * the line price and the snapshot - is read from the database here, so a tampered request can change
 * neither the price nor the composition name of the item. The whole selection is validated against the
 * catalog: the variant has to belong to the model, the decor, the glass and the surcharges have to be
 * active and attached to the model, and a variant without glass refuses a glass at all.
 *
 * The price of one piece is the documented formula
 *
 *   base_price_without_vat of the model + price_modifier of the variant + price_modifier of the decor
 *   + price_modifier of the glass + sum of price_without_vat of the surcharges
 *
 * and the line price is that amount times the quantity. VAT is applied to the rounded line price, so
 * the sum of the items always equals the recap of the inquiry to the cent.
 *
 * The same parts are stored in the snapshot as `price_breakdown`, together with their names and amounts.
 * That is what lets the configurator and the administration explain a higher price per piece ("the glass
 * adds 300 Kč") without reading the catalog of today, which may have changed in the meantime.
 */
class InquiryItemBuilder
{
    /**
     * @param  array<string, mixed>  $selection
     * @return array{
     *     door_model: DoorModel,
     *     door_variant: DoorVariant,
     *     decor: Decor|null,
     *     glass_type: GlassType|null,
     *     surcharges: Collection<int, Surcharge>,
     *     attributes: array{
     *         name: string,
     *         quantity: int,
     *         door_model_id: int,
     *         door_variant_id: int,
     *         decor_id: int|null,
     *         glass_type_id: int|null,
     *         unit_price_without_vat: float,
     *         price_without_vat: float,
     *         price_with_vat: float,
     *         configuration: array<string, mixed>
     *     }
     * }
     *
     * @throws ValidationException
     */
    public function build(array $selection, string $attributePrefix = ''): array
    {
        $doorModel = DoorModel::query()
            ->whereKey($selection['door_model_id'] ?? null)
            ->where('active', true)
            ->first();

        if (! $doorModel) {
            throw ValidationException::withMessages([
                $attributePrefix.'door_model_id' => 'Vybraný model není dostupný.',
            ]);
        }

        $doorVariant = DoorVariant::query()
            ->whereKey($selection['door_variant_id'] ?? null)
            ->where('door_model_id', $doorModel->id)
            ->first();

        if (! $doorVariant) {
            throw ValidationException::withMessages([
                $attributePrefix.'door_variant_id' => 'Vybraná varianta nepatří k zvolenému modelu.',
            ]);
        }

        $decor = $this->resolveDecor($doorModel, $selection['decor_id'] ?? null, $attributePrefix);
        $glassType = $this->resolveGlassType($doorModel, $doorVariant, $selection['glass_type_id'] ?? null, $attributePrefix);
        $surcharges = $this->resolveSurcharges($doorModel, $selection['surcharge_ids'] ?? [], $attributePrefix);

        $quantity = $this->quantity($selection['quantity'] ?? 1);

        $priceParts = $this->priceParts($doorModel, $doorVariant, $decor, $glassType, $surcharges);
        $unitPriceWithoutVat = $this->unitPrice($priceParts);

        $priceWithoutVat = round($unitPriceWithoutVat * $quantity, 2);
        $priceWithVat = VatCalculator::applyRate($priceWithoutVat);

        $configuration = [
            // The name and the quantity are repeated in the snapshot on purpose: the order level JSON
            // stores the items as plain arrays, so they stay readable without joining the item rows.
            'name' => $this->name($selection['name'] ?? null, $doorModel, $doorVariant),
            'quantity' => $quantity,
            // The exact composition of the price per piece: the base price of the model and every part that
            // was added to it, with the name and the amount that were valid when the inquiry was sent.
            'price_breakdown' => [
                'vat_rate' => VatCalculator::rate(),
                'unit_price_without_vat' => $unitPriceWithoutVat,
                'unit_price_with_vat' => VatCalculator::applyRate($unitPriceWithoutVat),
                'lines' => $priceParts,
            ],
            'door_model' => [
                'id' => $doorModel->id,
                'name' => $doorModel->name,
                'slug' => $doorModel->slug,
                'category' => $doorModel->category,
                'base_price_without_vat' => (float) $doorModel->base_price_without_vat,
            ],
            'door_variant' => [
                'id' => $doorVariant->id,
                'code' => $doorVariant->code,
                'width' => $doorVariant->width,
                'height' => $doorVariant->height,
                'opening_direction' => $doorVariant->opening_direction,
                'opening_type' => $doorVariant->opening_type,
                'has_glass' => $doorVariant->has_glass,
                'sliding_possible' => $doorVariant->sliding_possible,
                'price_modifier' => (float) $doorVariant->price_modifier,
            ],
            'decor' => $decor ? [
                'id' => $decor->id,
                'name' => $decor->name,
                'code' => $decor->code,
                'price_modifier' => (float) $decor->price_modifier,
            ] : null,
            'glass_type' => $glassType ? [
                'id' => $glassType->id,
                'name' => $glassType->name,
                'code' => $glassType->code,
                'opacity' => $glassType->opacity,
                'price_modifier' => (float) $glassType->price_modifier,
            ] : null,
            'surcharges' => $surcharges
                ->map(fn (Surcharge $surcharge): array => [
                    'id' => $surcharge->id,
                    'name' => $surcharge->name,
                    'code' => $surcharge->code,
                    'price_without_vat' => (float) $surcharge->price_without_vat,
                ])
                ->values()
                ->all(),
            'totals' => [
                'quantity' => $quantity,
                'unit_price_without_vat' => $unitPriceWithoutVat,
                'price_without_vat' => $priceWithoutVat,
                'price_with_vat' => $priceWithVat,
                'vat_rate' => VatCalculator::rate(),
            ],
        ];

        return [
            'door_model' => $doorModel,
            'door_variant' => $doorVariant,
            'decor' => $decor,
            'glass_type' => $glassType,
            'surcharges' => $surcharges,
            'attributes' => [
                'name' => $configuration['name'],
                'quantity' => $quantity,
                // The catalog references are stored next to the snapshot, so the administration can edit
                // the item with real selects without rewriting what the customer actually sent.
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $doorVariant->id,
                'decor_id' => $decor?->id,
                'glass_type_id' => $glassType?->id,
                'unit_price_without_vat' => $unitPriceWithoutVat,
                'price_without_vat' => $priceWithoutVat,
                'price_with_vat' => $priceWithVat,
                'configuration' => $configuration,
            ],
        ];
    }

    /**
     * The price per piece of a selection, resolved directly from the catalog.
     *
     * Unlike `build()` this never throws: it returns null only when the model or the variant is missing,
     * so the administration can price any combination the form currently shows. A decor, a glass or a
     * surcharge that the model does not offer simply adds nothing, which lets an administrator
     * recalculate a quote whose catalog entry has changed in the meantime.
     *
     * The formula is the same one `build()` uses, so a recalculated item is priced exactly like a newly
     * submitted one:
     *
     *   base_price_without_vat of the model + price_modifier of the variant + price_modifier of the decor
     *   + price_modifier of the glass + sum of price_without_vat of the surcharges
     *
     * @param  list<int>  $surchargeIds
     */
    public function catalogUnitPrice(
        ?int $doorModelId,
        ?int $doorVariantId,
        ?int $decorId,
        ?int $glassTypeId,
        array $surchargeIds = [],
    ): ?float {
        if ($doorModelId === null || $doorVariantId === null) {
            return null;
        }

        $doorModel = DoorModel::query()->whereKey($doorModelId)->first();

        if (! $doorModel) {
            return null;
        }

        $doorVariant = DoorVariant::query()
            ->whereKey($doorVariantId)
            ->where('door_model_id', $doorModel->id)
            ->first();

        if (! $doorVariant) {
            return null;
        }

        $decor = $decorId === null
            ? null
            : $doorModel->decors()->whereKey($decorId)->first();

        $glassType = (! $doorVariant->has_glass || $glassTypeId === null)
            ? null
            : $doorModel->glasses()->whereKey($glassTypeId)->first();

        $surcharges = $surchargeIds === []
            ? collect()
            : $doorModel->surcharges()->whereIn('surcharges.id', $surchargeIds)->get();

        return $this->unitPrice($this->priceParts($doorModel, $doorVariant, $decor, $glassType, $surcharges));
    }

    /**
     * The priced parts of one piece, in the order the documented formula adds them up.
     *
     * Each part carries the name and the code of the catalog entry it was read from, so the snapshot
     * describes the composition of the price on its own, without a join to the catalog and without the
     * risk that a later change of a name rewrites what the customer saw.
     *
     * A part is kept even when its amount is zero: the snapshot records that the customer selected the
     * option, while the presentation decides whether a zero amount is worth showing. The variant is
     * always part of the price, the decor, the glass and the surcharges only when they were selected.
     *
     * @param  Collection<int, Surcharge>  $surcharges
     * @return list<array{key: string, label: string, name: string|null, code: string|null, amount_without_vat: float}>
     */
    private function priceParts(
        DoorModel $doorModel,
        DoorVariant $doorVariant,
        ?Decor $decor,
        ?GlassType $glassType,
        Collection $surcharges,
    ): array {
        $parts = [
            [
                'key' => 'base_price',
                'label' => 'Základní cena dveří',
                'name' => null,
                'code' => null,
                'amount_without_vat' => (float) $doorModel->base_price_without_vat,
            ],
            [
                'key' => 'door_variant',
                'label' => 'Varianta',
                'name' => $doorVariant->code,
                'code' => $doorVariant->code,
                'amount_without_vat' => (float) $doorVariant->price_modifier,
            ],
        ];

        if ($decor) {
            $parts[] = [
                'key' => 'decor',
                'label' => 'Dekor',
                'name' => $decor->name,
                'code' => $decor->code,
                'amount_without_vat' => (float) $decor->price_modifier,
            ];
        }

        if ($glassType) {
            $parts[] = [
                'key' => 'glass_type',
                'label' => 'Sklo',
                'name' => $glassType->name,
                'code' => $glassType->code,
                'amount_without_vat' => (float) $glassType->price_modifier,
            ];
        }

        foreach ($surcharges as $surcharge) {
            $parts[] = [
                'key' => 'surcharge',
                'label' => 'Příplatek',
                'name' => $surcharge->name,
                'code' => $surcharge->code,
                'amount_without_vat' => (float) $surcharge->price_without_vat,
            ];
        }

        return $parts;
    }

    /**
     * The documented price of one piece, rounded to the cent.
     *
     * @param  list<array{amount_without_vat: float}>  $priceParts
     */
    private function unitPrice(array $priceParts): float
    {
        return round(array_sum(array_column($priceParts, 'amount_without_vat')), 2);
    }

    /**
     * The visitor may name the item freely ("Dveře do ložnice"), the catalog label is only the
     * fallback, so an item always has a readable name in the administration.
     */
    private function name(mixed $name, DoorModel $doorModel, DoorVariant $doorVariant): string
    {
        $submitted = is_string($name) ? trim($name) : '';

        if ($submitted !== '') {
            return mb_substr($submitted, 0, 160);
        }

        return trim($doorModel->name.' · '.$doorVariant->code);
    }

    private function quantity(mixed $quantity): int
    {
        $value = is_numeric($quantity) ? (int) $quantity : 0;

        return max(1, min($value, InquiryItem::MAX_QUANTITY_PER_ITEM));
    }

    /**
     * @throws ValidationException
     */
    private function resolveDecor(DoorModel $doorModel, mixed $decorId, string $attributePrefix): ?Decor
    {
        if (! $decorId) {
            return null;
        }

        $decor = Decor::query()
            ->whereKey($decorId)
            ->where('active', true)
            ->first();

        if (! $decor || ! $doorModel->decors()->whereKey($decor->id)->exists()) {
            throw ValidationException::withMessages([
                $attributePrefix.'decor_id' => 'Vybraný dekor není dostupný pro zvolený model.',
            ]);
        }

        return $decor;
    }

    /**
     * @throws ValidationException
     */
    private function resolveGlassType(DoorModel $doorModel, DoorVariant $doorVariant, mixed $glassTypeId, string $attributePrefix): ?GlassType
    {
        if (! $doorVariant->has_glass) {
            if ($glassTypeId) {
                throw ValidationException::withMessages([
                    $attributePrefix.'glass_type_id' => 'Vybraná varianta neumožňuje sklo.',
                ]);
            }

            return null;
        }

        if (! $glassTypeId) {
            return null;
        }

        $glassType = GlassType::query()
            ->whereKey($glassTypeId)
            ->where('active', true)
            ->first();

        if (! $glassType || ! $doorModel->glasses()->whereKey($glassType->id)->exists()) {
            throw ValidationException::withMessages([
                $attributePrefix.'glass_type_id' => 'Vybrané sklo není dostupné pro zvolený model.',
            ]);
        }

        return $glassType;
    }

    /**
     * @return Collection<int, Surcharge>
     *
     * @throws ValidationException
     */
    private function resolveSurcharges(DoorModel $doorModel, mixed $surchargeIds, string $attributePrefix): Collection
    {
        if (! is_array($surchargeIds) || $surchargeIds === []) {
            return collect();
        }

        $orderedSurchargeIds = array_values(array_map(intval(...), $surchargeIds));

        $surcharges = Surcharge::query()
            ->whereIn('id', $orderedSurchargeIds)
            ->where('active', true)
            ->get();

        if ($surcharges->count() !== count($orderedSurchargeIds)) {
            throw ValidationException::withMessages([
                $attributePrefix.'surcharge_ids' => 'Některé příplatky nejsou dostupné.',
            ]);
        }

        $allowedSurchargeIds = $doorModel->surcharges()
            ->whereIn('surcharges.id', $orderedSurchargeIds)
            ->pluck('surcharges.id');

        if ($allowedSurchargeIds->count() !== count($orderedSurchargeIds)) {
            throw ValidationException::withMessages([
                $attributePrefix.'surcharge_ids' => 'Vybrané příplatky nejsou dostupné pro zvolený model.',
            ]);
        }

        return $surcharges
            ->sortBy(fn (Surcharge $surcharge): int => (int) array_search($surcharge->id, $orderedSurchargeIds, true))
            ->values();
    }
}
