<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single editable configuration of the cookie dialog.
 *
 * The table is a singleton on purpose: the administration edits this one record and never creates a
 * second one. `version` is the consent version the browser stores next to the chosen categories: when
 * the version changes, every stored preference is considered outdated and the visitor is asked again.
 */
class CookieSetting extends Model
{
    /**
     * Version of the default wording. Bumping it invalidates the consent already stored in browsers.
     */
    public const DEFAULT_VERSION = '1.0';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'enabled',
        'version',
        'banner_title',
        'banner_description',
        'accept_all_label',
        'reject_all_label',
        'customize_label',
        'save_preferences_label',
        'necessary_title',
        'necessary_description',
        'analytics_title',
        'analytics_description',
        'marketing_title',
        'marketing_description',
        'preferences_title',
        'preferences_description',
        'footer_link_label',
        'privacy_policy_url',
        'cookie_policy_url',
    ];

    /**
     * The record the public dialog reads, created on first use when the seeder has not run yet.
     *
     * The oldest row is taken deliberately instead of an unordered `first()`, so a legacy database
     * with more than one row still resolves deterministically.
     */
    public static function current(): self
    {
        $setting = static::query()->oldest('id')->first();

        if ($setting instanceof self) {
            return $setting;
        }

        return static::query()->create(static::defaults());
    }

    /**
     * The wording the site publishes until an editor changes it.
     *
     * The text describes only what this application really does: necessary cookies of the Laravel
     * session and of the inquiry form, and optional categories that stay untouched until a script is
     * configured and the visitor agrees.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'version' => self::DEFAULT_VERSION,
            'banner_title' => 'Používáme cookies',
            'banner_description' => 'Nezbytné cookies zajišťují chod webu a odesílání formulářů. Analytické ani marketingové nástroje nespouštíme, dokud k tomu nedáte souhlas.',
            'accept_all_label' => 'Povolit vše',
            'reject_all_label' => 'Odmítnout volitelné',
            'customize_label' => 'Nastavit cookies',
            'save_preferences_label' => 'Uložit nastavení',
            'necessary_title' => 'Nezbytné cookies',
            'necessary_description' => 'Relace prohlížení, ochrana odesílaných formulářů a uložení vaší volby cookies. Bez nich web nefunguje, proto je nelze vypnout.',
            'analytics_title' => 'Analytické cookies',
            'analytics_description' => 'Pomáhají měřit návštěvnost webu. Spouštějí se jen po vašem souhlasu.',
            'marketing_title' => 'Marketingové cookies',
            'marketing_description' => 'Slouží k měření reklamních kampaní. Spouštějí se jen po vašem souhlasu.',
            'preferences_title' => 'Preferenční cookies',
            'preferences_description' => 'Pamatují si volitelné nastavení webu. Spouštějí se jen po vašem souhlasu.',
            'footer_link_label' => 'Nastavení cookies',
            'privacy_policy_url' => '/ochrana-osobnich-udalu',
            'cookie_policy_url' => '/zasady-cookies',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
