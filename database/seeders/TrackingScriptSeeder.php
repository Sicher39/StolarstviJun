<?php

namespace Database\Seeders;

use App\Enums\Compliance\ScriptPosition;
use App\Enums\Compliance\TrackingCategory;
use App\Models\TrackingScript;
use Illuminate\Database\Seeder;

/**
 * Seeds the cookies and services the site may use.
 *
 * Two groups of records:
 *
 * - the necessary first-party cookies of the application itself (session, form protection, the stored
 *   cookie choice). They carry no code, because the application sets them; the records exist so the
 *   cookie policy can explain them.
 * - a template for an analytical and for a marketing tool. Both start **disabled** and **without code**,
 *   so nothing is loaded and nothing is claimed before an operator pastes the snippet of a provider they
 *   actually use. No provider, identifier or vendor snippet is invented here.
 *
 * `name` is the stable, seed-owned key. A re-run refreshes the descriptive columns of an existing record
 * but never touches the operator's decisions: code, the on/off switch and the priority are preserved.
 */
class TrackingScriptSeeder extends Seeder
{
    /**
     * Columns the administration owns on an existing record.
     *
     * @var list<string>
     */
    private const ADMIN_MANAGED_COLUMNS = [
        'code',
        'enabled',
        'priority',
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const SCRIPTS = [
        [
            'name' => 'Relace prohlížení (Laravel session)',
            'category' => TrackingCategory::Necessary,
            'position' => ScriptPosition::Head,
            'description' => 'První strana. Uchovává relaci návštěvníka, aby web poznal stav stránky a mohl zobrazit výsledek odeslaného formuláře. Bez této technické cookie by web nefungoval.',
            'provider_name' => null,
            'provider_privacy_url' => null,
            'code' => null,
            'enabled' => true,
            'requires_consent' => false,
            'priority' => 10,
        ],
        [
            'name' => 'Ochrana formulářů (CSRF token)',
            'category' => TrackingCategory::Necessary,
            'position' => ScriptPosition::Head,
            'description' => 'První strana. Chrání odesílané formuláře, zejména poptávku z konfigurátoru, proti zneužití cizím webem. Jde o bezpečnostní opatření, nikoli o měření.',
            'provider_name' => null,
            'provider_privacy_url' => null,
            'code' => null,
            'enabled' => true,
            'requires_consent' => false,
            'priority' => 20,
        ],
        [
            'name' => 'Uložení volby cookies',
            'category' => TrackingCategory::Necessary,
            'position' => ScriptPosition::BodyEnd,
            'description' => 'První strana. Ukládá do prohlížeče anonymní identifikátor a zvolené kategorie cookies, aby se lišta nezobrazovala při každé návštěvě znovu.',
            'provider_name' => null,
            'provider_privacy_url' => null,
            'code' => null,
            'enabled' => true,
            'requires_consent' => false,
            'priority' => 30,
        ],
        [
            'name' => 'Analytický nástroj (vzor, vypnuto)',
            'category' => TrackingCategory::Analytics,
            'position' => ScriptPosition::BodyEnd,
            'description' => 'Vzorový záznam pro měření návštěvnosti. Dokud není zapnutý a není v něm vložen kód od zvoleného poskytovatele, nic se nespouští a v zásadách cookies se nezobrazuje.',
            'provider_name' => null,
            'provider_privacy_url' => null,
            'code' => null,
            'enabled' => false,
            'requires_consent' => true,
            'priority' => 40,
        ],
        [
            'name' => 'Marketingový nástroj (vzor, vypnuto)',
            'category' => TrackingCategory::Marketing,
            'position' => ScriptPosition::BodyEnd,
            'description' => 'Vzorový záznam pro měření reklamních kampaní. Dokud není zapnutý a není v něm vložen kód od zvoleného poskytovatele, nic se nespouští a v zásadách cookies se nezobrazuje.',
            'provider_name' => null,
            'provider_privacy_url' => null,
            'code' => null,
            'enabled' => false,
            'requires_consent' => true,
            'priority' => 50,
        ],
    ];

    public function run(): void
    {
        foreach (self::SCRIPTS as $script) {
            $existing = TrackingScript::query()->where('name', $script['name'])->first();

            if ($existing === null) {
                TrackingScript::query()->create($script);

                continue;
            }

            $existing
                ->fill(array_diff_key($script, array_flip(self::ADMIN_MANAGED_COLUMNS)))
                ->save();
        }
    }
}
