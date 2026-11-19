<?php

namespace App\Services\Compliance;

use App\Enums\Compliance\TrackingCategory;
use App\Models\CookieSetting;
use App\Models\LegalDocument;
use App\Models\TrackingScript;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The single JSON source the cookie dialog reads: its texts, the enabled cookies and services, the
 * links to the legal documents and the categories the visitor may actually configure.
 *
 * A category is offered only when it really carries an enabled script of an optional category. Without
 * such a script there is nothing to ask for, so `requiresCookieConsent` stays false and the dialog is
 * never rendered - the site then only uses the technical cookies its pages need.
 *
 * The legal links point at the stable public pages (`front.gdpr`, `front.cookies`), which always render
 * the currently published document, so a link can never point at a superseded version.
 */
class CookieConfigPayload
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $settings = $this->settings();
        $scripts = $this->scripts();
        $optionalCategories = $this->optionalCategories($scripts);

        return [
            'settings' => [
                'enabled' => (bool) $settings->enabled,
                'version' => (string) $settings->version,
                'bannerTitle' => $settings->banner_title,
                'bannerDescription' => $settings->banner_description,
                'acceptAllLabel' => $settings->accept_all_label,
                'rejectAllLabel' => $settings->reject_all_label,
                'customizeLabel' => $settings->customize_label,
                'savePreferencesLabel' => $settings->save_preferences_label,
                'necessaryTitle' => $settings->necessary_title,
                'necessaryDescription' => $settings->necessary_description,
                'analyticsTitle' => $settings->analytics_title,
                'analyticsDescription' => $settings->analytics_description,
                'marketingTitle' => $settings->marketing_title,
                'marketingDescription' => $settings->marketing_description,
                'preferencesTitle' => $settings->preferences_title,
                'preferencesDescription' => $settings->preferences_description,
                'footerLinkLabel' => $settings->footer_link_label,
                'privacyPolicyUrl' => $this->publicUrl($settings->privacy_policy_url, 'front.gdpr'),
                'cookiePolicyUrl' => $this->publicUrl($settings->cookie_policy_url, 'front.cookies'),
            ],
            'trackingScripts' => $scripts
                ->map(fn (TrackingScript $script): array => $script->toFrontendArray())
                ->values()
                ->all(),
            'legalLinks' => $this->legalLinks(),
            'optionalCategories' => $optionalCategories,
            'requiresCookieConsent' => (bool) $settings->enabled && $optionalCategories !== [],
        ];
    }

    /**
     * The stored settings, or the built-in defaults while the table does not exist yet.
     *
     * Reading a page must never fail because the panel has not been seeded: the dialog falls back to
     * the wording every page already uses.
     */
    public function settings(): CookieSetting
    {
        if (! Schema::hasTable('cookie_settings')) {
            return new CookieSetting(CookieSetting::defaults());
        }

        return CookieSetting::current();
    }

    /**
     * @return Collection<int, TrackingScript>
     */
    private function scripts(): Collection
    {
        if (! Schema::hasTable('tracking_scripts')) {
            return new Collection();
        }

        return TrackingScript::publishedScripts();
    }

    /**
     * The optional categories that carry at least one enabled script.
     *
     * @param  Collection<int, TrackingScript>  $scripts
     * @return list<string>
     */
    private function optionalCategories(Collection $scripts): array
    {
        $categories = [];

        foreach ($scripts as $script) {
            $category = $script->category;

            if (! $category instanceof TrackingCategory || ! $category->isOptional()) {
                continue;
            }

            if (! in_array($category->value, $categories, true)) {
                $categories[] = $category->value;
            }
        }

        return $categories;
    }

    /**
     * Title and version of both documents, so the dialog can name what the visitor is agreeing with.
     *
     * @return array<string, array{title: string, version: string, url: string}>
     */
    private function legalLinks(): array
    {
        return [
            'privacy_policy' => $this->legalLink(LegalDocument::TYPE_PRIVACY_POLICY, 'front.gdpr'),
            'cookie_policy' => $this->legalLink(LegalDocument::TYPE_COOKIE_POLICY, 'front.cookies'),
        ];
    }

    /**
     * @return array{title: string, version: string, url: string}
     */
    private function legalLink(string $type, string $routeName): array
    {
        $document = $this->document($type);

        return [
            'title' => $document->title,
            'version' => $document->version,
            'url' => route($routeName),
        ];
    }

    /**
     * The document the public page of a type renders: the published record, or the configured
     * baseline while the record is only a draft.
     */
    private function document(string $type): LegalDocument
    {
        if (! Schema::hasTable('legal_documents')) {
            return LegalDocument::baseline($type);
        }

        return LegalDocument::bodyFor($type);
    }

    /**
     * A configured address wins only when it points outside this application (an absolute URL is an
     * operator decision); otherwise the stable route of the document is published.
     */
    private function publicUrl(?string $configured, string $routeName): string
    {
        $configured = trim((string) $configured);

        if ($configured !== '' && preg_match('#^(?:https?:)?//#i', $configured) === 1) {
            return $configured;
        }

        return route($routeName);
    }
}
