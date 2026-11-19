<?php

namespace Database\Seeders;

use App\Models\CookieSetting;
use App\Models\TrackingScript;
use Illuminate\Database\Seeder;

/**
 * Seeds the single cookie-settings record the dialog reads.
 *
 * Three kinds of value are treated differently, because the seeder must be safe to re-run:
 *
 * - the banner and category wording is seed-owned: a blank value is filled and the seeded default is
 *   refreshed, while a text the operator wrote is preserved;
 * - the consent version and the on/off switch are operational: a re-run never bumps the version (that
 *   would invalidate every stored consent and re-prompt every visitor) and never re-enables a banner
 *   the operator switched off;
 * - the two policy URLs fall back to the routes that publish the documents, while an absolute URL is an
 *   operator override and is kept.
 *
 * The banner description mentions the number of optional cookies the site really uses, so the text can
 * not claim an analytics tool that is not configured.
 */
class CookieSettingSeeder extends Seeder
{
    /**
     * Text fields this seeder owns.
     *
     * @var list<string>
     */
    private const COPY_FIELDS = [
        'banner_title',
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
    ];

    public function run(): void
    {
        $defaults = CookieSetting::defaults();
        $setting = CookieSetting::query()->first();

        if ($setting === null) {
            CookieSetting::query()->create($defaults);

            return;
        }

        foreach (self::COPY_FIELDS as $field) {
            $current = trim((string) $setting->{$field});

            if ($current === '' || $current === (string) $defaults[$field]) {
                $setting->{$field} = $defaults[$field];
            }
        }

        $setting->banner_description = $this->resolveBannerDescription(
            $setting->banner_description,
            (string) $defaults['banner_description'],
            TrackingScript::optionalCategoryCounts(),
        );

        $setting->privacy_policy_url = $this->resolvePolicyUrl($setting->privacy_policy_url, (string) $defaults['privacy_policy_url']);
        $setting->cookie_policy_url = $this->resolvePolicyUrl($setting->cookie_policy_url, (string) $defaults['cookie_policy_url']);

        $setting->save();
    }

    /**
     * The banner description carries the live count of optional cookies, so it is regenerated unless the
     * operator replaced the whole sentence with their own text.
     *
     * @param  array<string, int>  $counts
     */
    private function resolveBannerDescription(?string $current, string $base, array $counts): string
    {
        $current = trim((string) $current);

        if ($current !== '' && ! str_starts_with($current, $base)) {
            return $current;
        }

        return $this->buildBannerDescription($base, $counts);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function buildBannerDescription(string $base, array $counts): string
    {
        $labels = [
            'analytics' => 'analytické',
            'marketing' => 'marketingové',
            'preferences' => 'preferenční',
        ];

        $parts = [];

        foreach ($labels as $category => $label) {
            $count = (int) ($counts[$category] ?? 0);

            if ($count > 0) {
                $parts[] = $label.' ('.$count.')';
            }
        }

        if ($parts === []) {
            return $base;
        }

        return $base.' Volitelné cookies, které si můžete povolit: '.implode(', ', $parts).'.';
    }

    /**
     * A configured absolute URL points outside this application and stays; any other value addresses a
     * page of this application, which is what the seeder owns and corrects when it is stale.
     */
    private function resolvePolicyUrl(?string $current, string $canonical): string
    {
        $current = trim((string) $current);

        if ($current === '' || preg_match('#^(?:https?:)?//#i', $current) !== 1) {
            return $canonical;
        }

        return $current;
    }
}
