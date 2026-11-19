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

        $doorModel = $this->section($configuration, 'door_model');
        $doorVariant = $this->section($configuration, 'door_variant');
        $decor = $this->section($configuration, 'decor');
        $glassType = $this->section($configuration, 'glass_type');
        $totals = $this->section($configuration, 'totals');

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
            $this->line('Spočtená cena bez DPH', $this->money($totals['price_without_vat'] ?? null)),
            $this->line('Spočtená cena s DPH', $this->totalsWithVatText($totals)),
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
