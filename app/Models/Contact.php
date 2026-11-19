<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single editable record with the data rendered on the public contact page.
 *
 * The table is a singleton on purpose: the Filament page edits this one record and never creates another one.
 */
class Contact extends Model
{
    protected $fillable = [
        'company_name',
        'street',
        'city',
        'contact_person',
        'email',
        'phone',
        'registration_number',
        'vat_number',
        'data_box',
        'bank_account',
        'registry_note',
        'google_maps_url',
        'waze_url',
        'seznam_maps_url',
    ];

    /**
     * Baseline values that keep the currently published contact page content.
     *
     * @return array<string, string>
     */
    public static function defaultAttributes(): array
    {
        return [
            'company_name' => 'Stolařství Jun a synové s.r.o.',
            'street' => 'Stará Osada 540',
            'city' => '664 84 Zastávka u Brna',
            'contact_person' => 'Miroslav Jun',
            'email' => 'info@drevovyroba-jun.cz',
            'phone' => '+420 777 123 123',
            'registration_number' => '28288505',
            'vat_number' => 'CZ28288505',
            'data_box' => '2bc4vfu',
            'bank_account' => 'xxxxxxxxx/xxxx',
            'registry_note' => 'Zapsána pod značkou C 58837/KSBR Krajským soudem v Brně.',
            'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=Star%C3%A1%20Osada%20540%2C%20664%2084%20Zast%C3%A1vka%20u%20Brna',
            'waze_url' => 'https://www.waze.com/ul?q=Star%C3%A1%20Osada%20540%2C%20664%2084%20Zast%C3%A1vka%20u%20Brna&navigate=yes',
            'seznam_maps_url' => 'https://mapy.cz/zakladni?q=Star%C3%A1%20Osada%20540%2C%20664%2084%20Zast%C3%A1vka%20u%20Brna',
        ];
    }

    /**
     * Resolve the singleton record and only create the baseline row when the table is still empty.
     *
     * The oldest row is taken deliberately instead of an unordered `first()`, so the result stays deterministic
     * even when a legacy database already contains more than one row.
     */
    public static function current(): self
    {
        $contact = static::query()->oldest('id')->first();

        if ($contact instanceof self) {
            return $contact;
        }

        return static::query()->create(static::defaultAttributes());
    }
}
