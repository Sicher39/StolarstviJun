<?php

namespace App\Services;

class VatCalculator
{
    /**
     * VAT rate in percent as configured for pricing.
     */
    public static function rate(): float
    {
        return (float) config('pricing.vat_rate');
    }

    /**
     * Converts a net price to a gross price using the configured VAT rate.
     */
    public static function applyRate(float $priceWithoutVat): float
    {
        return round($priceWithoutVat * (1 + self::rate() / 100), 2);
    }

    /**
     * The configured rate as a label, for example "21 %". The administration shows it next to every
     * calculated gross price, so no screen can claim a rate the calculation does not use.
     */
    public static function formattedRate(): string
    {
        $rate = rtrim(rtrim(number_format(self::rate(), 2, ',', ' '), '0'), ',');

        return $rate.' %';
    }
}
