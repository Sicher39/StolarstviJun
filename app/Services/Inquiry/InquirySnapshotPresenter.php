<?php

namespace App\Services\Inquiry;

/**
 * Reads the `configuration` JSON snapshot of an inquiry as human readable lines.
 *
 * The snapshot is written once by the configurator (see ConfiguratorInquiryController) and keeps the
 * names, codes and prices that were valid when the inquiry was sent, so it is the historical record
 * of the quote. The administration therefore shows it as text instead of an editable JSON textarea:
 * nobody has to edit JSON by hand and a later change of the catalog can not rewrite what the customer
 * actually selected.
 *
 * Three views are produced from the same snapshot:
 *
 *  - `itemLines()` describes the selection of one configured door (model, variant, decor, glass,
 *    surcharges and the modifiers they add),
 *  - `itemPriceLines()` adds the price per piece and the line price of that door,
 *  - `summaryLines()` is the recap of the whole request: the number of doors and the price without
 *    VAT, the VAT amount and the price with VAT.
 *
 * `lines()` keeps rendering the single door view of an inquiry stored before the items existed, so an
 * invoice-like reading of an old record never changes.
 */
class InquirySnapshotPresenter
{
    /**
     * Readable label and value pairs of the snapshot, without the empty ones.
     *
     * @param  array<string, mixed>|null  $configuration
     * @return list<array{label: string, value: string}>
     */
    public function lines(?array $configuration): array
    {
        if (blank($configuration)) {
            return [];
        }

        $totals = $this->section($configuration, 'totals');

        return array_values(array_filter([
            ...$this->itemLines($configuration),
            $this->line('Spočtená cena bez DPH', $this->money($totals['price_without_vat'] ?? null)),
            $this->line('Spočtená cena s DPH', $this->totalsWithVatText($totals)),
        ]));
    }

    /**
     * The selection of one configured door, without any price of the whole inquiry.
     *
     * @param  array<string, mixed>|null  $configuration
     * @return list<array{label: string, value: string}>
     */
    public function itemLines(?array $configuration): array
    {
        if (blank($configuration)) {
            return [];
        }

        $doorModel = $this->section($configuration, 'door_model');
        $doorVariant = $this->section($configuration, 'door_variant');
        $decor = $this->section($configuration, 'decor');
        $glassType = $this->section($configuration, 'glass_type');

        return array_values(array_filter([
            $this->line('Model dveří', $this->text($doorModel['name'] ?? null)),
            $this->line('Varianta', $this->variantText($doorVariant)),
            $this->line('Typ otevírání', $this->text($doorVariant['opening_type'] ?? null)),
            $this->line('Směr otevírání', $this->text($doorVariant['opening_direction'] ?? null)),
            $this->line('Dekor', $this->namedCodeText($decor)),
            $this->line('Sklo', $this->glassText($glassType)),
            $this->line('Příplatky', $this->surchargeText($configuration)),
            $this->line('Základní cena modelu bez DPH', $this->money($doorModel['base_price_without_vat'] ?? null)),
            $this->line('Příplatek varianty bez DPH', $this->money($doorVariant['price_modifier'] ?? null)),
            $this->line('Příplatek dekoru bez DPH', $this->money($decor['price_modifier'] ?? null)),
            $this->line('Příplatek skla bez DPH', $this->money($glassType['price_modifier'] ?? null)),
        ]));
    }

    /**
     * The quantity and the two price levels of one configured door. The price per piece is always
     * shown next to the line price, so a misread quantity can not look like a wrong unit price.
     *
     * @param  array<string, mixed>|null  $configuration
     * @return list<array{label: string, value: string}>
     */
    public function itemPriceLines(?array $configuration): array
    {
        if (blank($configuration)) {
            return [];
        }

        $totals = $this->section($configuration, 'totals');
        $quantity = $totals['quantity'] ?? $configuration['quantity'] ?? null;

        return array_values(array_filter([
            $this->line('Množství', $this->pieceCount($quantity)),
            $this->line('Cena za kus bez DPH', $this->money($totals['unit_price_without_vat'] ?? null)),
            $this->line('Cena celkem bez DPH', $this->money($totals['price_without_vat'] ?? null)),
            $this->line('Cena celkem s DPH', $this->money($totals['price_with_vat'] ?? null)),
        ]));
    }

    /**
     * The recap of the whole request.
     *
     * The older one-piece snapshot has no `summary` block: the configurator of that time asked for one
     * door only and had no quantity at all, so the recap falls back to its `totals` and counts one door.
     *
     * @param  array<string, mixed>|null  $configuration
     * @return list<array{label: string, value: string}>
     */
    public function summaryLines(?array $configuration): array
    {
        if (blank($configuration)) {
            return [];
        }

        $summary = $this->section($configuration, 'summary');
        $totals = $this->section($configuration, 'totals');

        $priceWithoutVat = $summary['price_without_vat'] ?? $totals['price_without_vat'] ?? null;
        $priceWithVat = $summary['price_with_vat'] ?? $totals['price_with_vat'] ?? null;
        $vatRate = $summary['vat_rate'] ?? $totals['vat_rate'] ?? null;
        $totalDoors = $summary['total_doors'] ?? ($this->section($configuration, 'door_model') === [] ? null : 1);
        $vatAmount = $summary['vat_amount'] ?? (
            is_numeric($priceWithoutVat) && is_numeric($priceWithVat)
                ? round((float) $priceWithVat - (float) $priceWithoutVat, 2)
                : null
        );

        return $this->totalsLines([
            'total_doors' => $totalDoors,
            'price_without_vat' => $priceWithoutVat,
            'vat_amount' => $vatAmount,
            'price_with_vat' => $priceWithVat,
            'vat_rate' => $vatRate,
        ]);
    }

    /**
     * The recap of an inquiry rendered from plain figures instead of a snapshot.
     *
     * The administration shows the live sum of the items through this method, so the recap of a request
     * that was edited by hand is described exactly like the recap the customer saw.
     *
     * @param  array{total_doors?: mixed, price_without_vat?: mixed, vat_amount?: mixed, price_with_vat?: mixed, vat_rate?: mixed}  $totals
     * @return list<array{label: string, value: string}>
     */
    public function totalsLines(array $totals): array
    {
        return array_values(array_filter([
            $this->line('Celkem dveří', $this->pieceCount($totals['total_doors'] ?? null)),
            $this->line('Cena bez DPH', $this->money($totals['price_without_vat'] ?? null)),
            $this->line('DPH', $this->money($totals['vat_amount'] ?? null)),
            $this->line('Cena s DPH', $this->money($totals['price_with_vat'] ?? null)),
            $this->line('Sazba DPH', $this->vatRateText($totals['vat_rate'] ?? null)),
        ]));
    }

    /**
     * One sentence with the totals the configurator computed, so a manual price change can be
     * compared with the price the customer saw.
     *
     * @param  array<string, mixed>|null  $configuration
     */
    public function computedTotals(?array $configuration): ?string
    {
        $totals = $this->section($configuration, 'totals');

        if ($totals === []) {
            $totals = $this->section($configuration, 'summary');
        }

        $withoutVat = $this->money($totals['price_without_vat'] ?? null);
        $withVat = $this->money($totals['price_with_vat'] ?? null);

        $parts = array_values(array_filter([
            $withoutVat === null ? null : $withoutVat.' bez DPH',
            $withVat === null ? null : $withVat.' s DPH',
        ]));

        if ($parts === []) {
            return null;
        }

        $rate = $this->percentage($totals['vat_rate'] ?? null);

        return 'Konfigurátor spočítal '.implode(' / ', $parts)
            .($rate === null ? '' : ' (sazba DPH '.$rate.' %)').'.';
    }

    /**
     * The block of the snapshot, or an empty array when it is not an array in the stored JSON.
     *
     * @param  array<string, mixed>|null  $configuration
     * @return array<string, mixed>
     */
    private function section(?array $configuration, string $key): array
    {
        $value = $configuration[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $doorVariant
     */
    private function variantText(array $doorVariant): ?string
    {
        return $this->joinText([
            $this->text($doorVariant['code'] ?? null),
            $this->dimensionsText($doorVariant),
        ]);
    }

    /**
     * @param  array<string, mixed>  $doorVariant
     */
    private function dimensionsText(array $doorVariant): ?string
    {
        $width = $doorVariant['width'] ?? null;
        $height = $doorVariant['height'] ?? null;

        if (! is_numeric($width) || ! is_numeric($height)) {
            return null;
        }

        return (int) $width.' × '.(int) $height.' mm';
    }

    /**
     * @param  array<string, mixed>  $glassType
     */
    private function glassText(array $glassType): ?string
    {
        $namedCode = $this->namedCodeText($glassType);
        $opacity = $glassType['opacity'] ?? null;

        if (! is_numeric($opacity)) {
            return $namedCode;
        }

        return $this->joinText([$namedCode, 'krytí '.(int) $opacity.' %']);
    }

    /**
     * "Name (code)", or only the part that is filled in.
     *
     * @param  array<string, mixed>  $section
     */
    private function namedCodeText(array $section): ?string
    {
        $name = $this->text($section['name'] ?? null);
        $code = $this->text($section['code'] ?? null);

        if ($name === null || $code === null || $name === $code) {
            return $name ?? $code;
        }

        return $name.' ('.$code.')';
    }

    /**
     * Surcharges with the prices that were valid at the time of the inquiry.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function surchargeText(array $configuration): ?string
    {
        $surcharges = $configuration['surcharges'] ?? null;

        if (! is_array($surcharges)) {
            return null;
        }

        $labels = [];

        foreach ($surcharges as $surcharge) {
            if (! is_array($surcharge)) {
                continue;
            }

            $name = $this->text($surcharge['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $price = $this->money($surcharge['price_without_vat'] ?? null);

            $labels[] = $price === null ? $name : $name.' ('.$price.')';
        }

        return $labels === [] ? null : implode('; ', $labels);
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    private function totalsWithVatText(array $totals): ?string
    {
        $withVat = $this->money($totals['price_with_vat'] ?? null);
        $rate = $this->percentage($totals['vat_rate'] ?? null);

        if ($withVat === null) {
            return null;
        }

        return $rate === null ? $withVat : $withVat.' ('.$rate.' %)';
    }

    /**
     * The Czech unit "ks" is invariant, so the number of doors needs no plural handling.
     */
    private function pieceCount(mixed $quantity): ?string
    {
        if (! is_numeric($quantity)) {
            return null;
        }

        return (int) $quantity.' ks';
    }

    private function vatRateText(mixed $vatRate): ?string
    {
        $rate = $this->percentage($vatRate);

        return $rate === null ? null : $rate.' %';
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    private function joinText(array $parts): ?string
    {
        $filledParts = array_values(array_filter($parts, fn (?string $part): bool => filled($part)));

        return $filledParts === [] ? null : implode(' · ', $filledParts);
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private function line(string $label, ?string $value): ?array
    {
        return filled($value) ? ['label' => $label, 'value' => $value] : null;
    }

    private function text(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function money(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, ',', ' ').' Kč';
    }

    private function percentage(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',');
    }
}
