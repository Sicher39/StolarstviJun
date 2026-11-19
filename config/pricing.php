<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the VAT rate used by the configurator and the
    | inquiry pricing. The value is a percentage (21 means 21 %).
    |
    */

    'vat_rate' => (float) env('PRICING_VAT_RATE', 21),

];
