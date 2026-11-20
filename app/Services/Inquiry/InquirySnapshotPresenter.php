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
 * Four views are produced from the same snapshot:
 *
 *  - `itemLines()` describes what was selected for one configured door (model, variant, decor, glass and
 *    the names of the surcharges), without prices,
 *  - `priceBreakdownLines()` explains the price of one piece: the base price of the model and every part
 *    that was added to it, each with its name and its amount,
 *  - `itemPriceLines()` adds the price per piece and the line price of that door,
 *  - `summaryLines()` is the recap of the whole request: the number of doors and the price without
 *    VAT, the VAT amount and the price with VAT.
 *
 * `lines()` keeps rendering the single door view of an inquiry stored before the items existed, so an
 * invoice-like reading of an old record never changes. `storedPriceComparison()` is the bridge to the
 * editable side: it says how the price currently stored in the administration differs from the snapshot
 * without touching the snapshot itself.
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
            ...$this->priceBreakdownLines($configuration),
            $this->line('Spočtená cena bez DPH', $this->money($totals['price_without_vat'] ?? null)),
            $this->line('Spočtená cena s DPH', $this->totalsWithVatText($totals)),
        ]));
    }

    /**
     * What was selected for one configured door, without any money.
     *
     * The prices of the selected options belong to `priceBreakdownLines()`, so the same amount is never
     * printed twice next to the same door.
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
        ]));
    }

    /**
     * The exact composition of the price of one piece, as it was charged.
     *
     * Every amount is net (without VAT) and names the option that added it, so a higher price per piece is
     * explained by the selection itself ("Sklo Čiré (GLA-0001) bez DPH: 300,00 Kč") instead of a jump of
     * the total. The lines add up to the price per piece that `itemPriceLines()` shows.
     *
     * A snapshot stored before the breakdown was written has no `price_breakdown` block; its parts are then
     * read from the model, variant, decor, glass and surcharge blocks that it has always carried, so an old
     * inquiry reads exactly like a new one.
     *
     * @param  array<string, mixed>|null  $configuration
     * @return list<array{label: string, value: string}>
     */
    public function priceBreakdownLines(?array $configuration): array
    {
        if (blank($configuration)) {
            return [];
        }

        $lines = [];

        foreach ($this->priceParts($configuration) as $part) {
            $amount = $part['amount_without_vat'] ?? null;

            if (! is_numeric($amount)) {
                continue;
            }

            $lines[] = [
                'label' => $this->pricePartLabel($part),
                'value' => $this->pricePartValue($part, (float) $amount),
            ];
        }

        return $lines;
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
     * One sentence comparing the price stored in the administration with the snapshot of the same item.
     *
     * The workshop may recalculate an item from the current catalog or type a different price per piece,
     * while the snapshot has to stay the record of what the customer was quoted. This sentence therefore
     * prints both numbers when they differ, so the change is visible without rewriting the snapshot. A
     * snapshot that still matches the stored price needs no sentence at all, which keeps the block quiet.
     *
     * @param  array<string, mixed>|null  $configuration
     * @param  array{quantity?: mixed, unit_price_without_vat?: mixed, price_without_vat?: mixed, price_with_vat?: mixed}  $stored
     */
    public function storedPriceComparison(?array $configuration, array $stored): ?string
    {
        $totals = $this->section($configuration, 'totals');

        if ($totals === []) {
            $totals = $this->section($configuration, 'summary');
        }

        $snapshotUnitPrice = $totals['unit_price_without_vat'] ?? null;
        $storedUnitPrice = $stored['unit_price_without_vat'] ?? null;

        if (! is_numeric($snapshotUnitPrice) || ! is_numeric($storedUnitPrice)) {
            return null;
        }

        if (abs((float) $snapshotUnitPrice - (float) $storedUnitPrice) < 0.005) {
            return null;
        }

        $storedUnit = $this->money($storedUnitPrice);
        $snapshotUnit = $this->money($snapshotUnitPrice);

        if ($storedUnit === null || $snapshotUnit === null) {
            return null;
        }

        return 'Aktuálně uložená cena se liší od snapshotu: '.$storedUnit.' za kus'
            .$this->storedLineText($stored)
            .'; při odeslání to bylo '.$snapshotUnit.' za kus.'
            .' Snapshot z odeslání zůstává nezměněný.';
    }

    /**
     * The quantity and the two line prices currently stored in the administration, as one parenthesis.
     *
     * @param  array<string, mixed>  $stored
     */
    private function storedLineText(array $stored): string
    {
        $quantity = $this->pieceCount($stored['quantity'] ?? null);
        $withoutVat = $this->money($stored['price_without_vat'] ?? null);
        $withVat = $this->money($stored['price_with_vat'] ?? null);

        $parts = array_values(array_filter([
            $quantity,
            $withoutVat === null ? null : $withoutVat.' bez DPH',
            $withVat === null ? null : $withVat.' s DPH',
        ]));

        return $parts === [] ? '' : ' ('.implode(' · ', $parts).')';
    }

    /**
     * The priced parts of one piece: the stored breakdown when the snapshot carries one, otherwise the very
     * same parts read from the blocks of the older snapshot.
     *
     * The older snapshot has no `price_breakdown`, but it has always kept the model, the variant, the decor,
     * the glass and the surcharges with their price modifiers, which is exactly the data the breakdown
     * describes. Reading them here means an inquiry sent before the breakdown existed is explained by the
     * same lines as a new one.
     *
     * @param  array<string, mixed>  $configuration
     * @return list<array{key?: mixed, label?: mixed, name?: mixed, code?: mixed, amount_without_vat?: mixed}>
     */
    private function priceParts(array $configuration): array
    {
        $storedLines = $this->section($configuration, 'price_breakdown')['lines'] ?? null;

        if (is_array($storedLines) && $storedLines !== []) {
            return array_values(array_filter($storedLines, is_array(...)));
        }

        $doorModel = $this->section($configuration, 'door_model');
        $doorVariant = $this->section($configuration, 'door_variant');
        $decor = $this->section($configuration, 'decor');
        $glassType = $this->section($configuration, 'glass_type');

        $parts = [
            $this->pricePart('base_price', 'Základní cena dveří', null, null, $doorModel['base_price_without_vat'] ?? null),
        ];

        if ($doorVariant !== []) {
            $parts[] = $this->pricePart(
                'door_variant',
                'Varianta',
                $doorVariant['code'] ?? null,
                $doorVariant['code'] ?? null,
                $doorVariant['price_modifier'] ?? null,
            );
        }

        if ($decor !== []) {
            $parts[] = $this->pricePart(
                'decor',
                'Dekor',
                $decor['name'] ?? null,
                $decor['code'] ?? null,
                $decor['price_modifier'] ?? null,
            );
        }

        if ($glassType !== []) {
            $parts[] = $this->pricePart(
                'glass_type',
                'Sklo',
                $glassType['name'] ?? null,
                $glassType['code'] ?? null,
                $glassType['price_modifier'] ?? null,
            );
        }

        foreach ($this->surchargeSections($configuration) as $surcharge) {
            $parts[] = $this->pricePart(
                'surcharge',
                'Příplatek',
                $surcharge['name'] ?? null,
                $surcharge['code'] ?? null,
                $surcharge['price_without_vat'] ?? null,
            );
        }

        return $parts;
    }

    /**
     * @return array{key: string, label: string, name: mixed, code: mixed, amount_without_vat: mixed}
     */
    private function pricePart(string $key, string $label, mixed $name, mixed $code, mixed $amountWithoutVat): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'name' => $name,
            'code' => $code,
            'amount_without_vat' => $amountWithoutVat,
        ];
    }

    /**
     * "Dekor Dub přírodní (DEC-0001) bez DPH": the kind of the part, the option that was chosen and the price
     * level of the amount, so the value never has to be guessed.
     *
     * @param  array{label?: mixed, name?: mixed, code?: mixed}  $part
     */
    private function pricePartLabel(array $part): string
    {
        $label = $this->text($part['label'] ?? null) ?? 'Cena';
        $option = $this->namedCodeText(['name' => $part['name'] ?? null, 'code' => $part['code'] ?? null]);

        return ($option === null ? $label : $label.' '.$option).' bez DPH';
    }

    /**
     * A selected option that costs nothing is described in words: a column of zeros would hide the parts of
     * the price that do matter, while the fact that the customer chose the option is worth keeping.
     *
     * @param  array{key?: mixed}  $part
     */
    private function pricePartValue(array $part, float $amount): string
    {
        if (round($amount, 2) === 0.0 && ($part['key'] ?? null) !== 'base_price') {
            return 'bez příplatku';
        }

        return $this->money($amount) ?? '';
    }

    /**
     * The surcharge blocks of the snapshot, skipping anything that is not an array.
     *
     * @param  array<string, mixed>  $configuration
     * @return list<array<string, mixed>>
     */
    private function surchargeSections(array $configuration): array
    {
        $surcharges = $configuration['surcharges'] ?? null;

        if (! is_array($surcharges)) {
            return [];
        }

        return array_values(array_filter($surcharges, is_array(...)));
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
     * The names of the surcharges that were selected; their amounts are part of the price breakdown, which
     * is rendered next to this list, so a name never carries a price twice.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function surchargeText(array $configuration): ?string
    {
        $names = [];

        foreach ($this->surchargeSections($configuration) as $surcharge) {
            $name = $this->text($surcharge['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $names[] = $name;
        }

        return $names === [] ? null : implode('; ', $names);
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
