<?php

namespace App\Services\Inquiry;

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Services\VatCalculator;

/**
 * Recomputes the prices of an inquiry from its items.
 *
 * A request may carry several separately configured doors, so the items are the source of truth: every
 * item keeps its own quantity and its own price per piece, its line price is the two multiplied and the
 * VAT is applied to the rounded line price. The inquiry only mirrors the sum of its items, so a manual
 * edit in the administration can never leave the recap and the lines disagreeing, and the customer sees
 * the same numbers that the workshop quotes.
 *
 * The recalculation is deliberately server side and runs on every save of the inquiry: the browser only
 * ever sends a quantity and a price per piece, never a total.
 *
 * An inquiry stored before the items existed has no rows here. Its manually edited prices are left
 * untouched, which keeps every legacy record readable exactly as it was stored.
 */
class InquiryTotalsCalculator
{
    /**
     * Recompute the line prices of every item, then mirror their sum into the inquiry.
     *
     * The four catalog references of the inquiry are kept in step with the first item, because every
     * reader of the older one-piece shape (the list table, the preview resolver, an export) still reads
     * them there.
     */
    public function recalculate(Inquiry $inquiry): void
    {
        $items = $inquiry->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $priceWithoutVat = 0.0;
        $priceWithVat = 0.0;

        foreach ($items as $item) {
            $quantity = $this->quantity($item);
            $linePriceWithoutVat = round((float) $item->unit_price_without_vat * $quantity, 2);
            $linePriceWithVat = VatCalculator::applyRate($linePriceWithoutVat);

            $this->storeItem($item, $quantity, $linePriceWithoutVat, $linePriceWithVat);

            $priceWithoutVat += $linePriceWithoutVat;
            $priceWithVat += $linePriceWithVat;
        }

        $inquiry->fill([
            'price_without_vat' => round($priceWithoutVat, 2),
            'price_with_vat' => round($priceWithVat, 2),
            ...$this->mirroredSelection($items->first()),
        ])->save();

        // The form re-renders right after the save and reads its recap from this relation, so the
        // relation has to hold the rows with the prices that were just computed instead of the values
        // the repeater saved a moment ago.
        $inquiry->setRelation('items', $items);
    }

    /**
     * The recap of the inquiry without writing anything, so the form can show it before it is saved.
     *
     * An inquiry without items falls back to its own price columns and counts one door, exactly like
     * `Inquiry::totalDoors()`.
     *
     * @return array{total_doors: int, price_without_vat: float, vat_amount: float, price_with_vat: float, vat_rate: float}
     */
    public function summary(Inquiry $inquiry): array
    {
        $items = $inquiry->relationLoaded('items') ? $inquiry->items : $inquiry->items()->get();

        if ($items->isEmpty()) {
            $priceWithoutVat = round((float) $inquiry->price_without_vat, 2);
            $priceWithVat = round((float) $inquiry->price_with_vat, 2);

            return [
                'total_doors' => 1,
                'price_without_vat' => $priceWithoutVat,
                'vat_amount' => round($priceWithVat - $priceWithoutVat, 2),
                'price_with_vat' => $priceWithVat,
                'vat_rate' => VatCalculator::rate(),
            ];
        }

        $totalDoors = 0;
        $priceWithoutVat = 0.0;
        $priceWithVat = 0.0;

        foreach ($items as $item) {
            $totalDoors += $this->quantity($item);
            $priceWithoutVat += (float) $item->price_without_vat;
            $priceWithVat += (float) $item->price_with_vat;
        }

        $priceWithoutVat = round($priceWithoutVat, 2);
        $priceWithVat = round($priceWithVat, 2);

        return [
            'total_doors' => $totalDoors,
            'price_without_vat' => $priceWithoutVat,
            'vat_amount' => round($priceWithVat - $priceWithoutVat, 2),
            'price_with_vat' => $priceWithVat,
            'vat_rate' => VatCalculator::rate(),
        ];
    }

    /**
     * A quantity outside the documented range is corrected instead of stored: the configurator refuses
     * it and the administration form limits it, so a value outside can only come from older code or a
     * hand written query.
     */
    private function quantity(InquiryItem $item): int
    {
        return max(1, min((int) $item->quantity, InquiryItem::MAX_QUANTITY_PER_ITEM));
    }

    /**
     * Only the columns that really changed are written, so saving an inquiry without a price change
     * does not touch every item row.
     */
    private function storeItem(InquiryItem $item, int $quantity, float $linePriceWithoutVat, float $linePriceWithVat): void
    {
        $isUnchanged = ((int) $item->quantity === $quantity)
            && (abs((float) $item->price_without_vat - $linePriceWithoutVat) < 0.005)
            && (abs((float) $item->price_with_vat - $linePriceWithVat) < 0.005);

        if ($isUnchanged) {
            return;
        }

        $item->forceFill([
            'quantity' => $quantity,
            'price_without_vat' => $linePriceWithoutVat,
            'price_with_vat' => $linePriceWithVat,
        ])->save();
    }

    /**
     * The catalog references of the first item, which is what the columns of `inquiries` have always
     * described. An item stored before the columns existed has no references of its own, so the values
     * already on the inquiry are kept instead of being cleared.
     *
     * @return array<string, int|null>
     */
    private function mirroredSelection(?InquiryItem $firstItem): array
    {
        if (! $firstItem instanceof InquiryItem) {
            return [];
        }

        $selection = [];

        foreach (['door_model_id', 'door_variant_id', 'decor_id', 'glass_type_id'] as $attribute) {
            if ($firstItem->{$attribute} === null) {
                continue;
            }

            $selection[$attribute] = (int) $firstItem->{$attribute};
        }

        return $selection;
    }
}
