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
}
