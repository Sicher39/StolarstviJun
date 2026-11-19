<?php

namespace Tests\Feature;

use App\Enums\Compliance\ConsentType;
use App\Enums\Compliance\TrackingCategory;
use App\Models\ConsentRecord;
use App\Models\TrackingScript;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The JSON API of the cookie dialog.
 *
 * The payload decides what the dialog may offer and the consent endpoint stores the choice. Both are
 * checked against the real records, because the important property of the feature is that the dialog can
 * never ask for a category the site does not use and never stores a consent the visitor did not give.
 */
class CookieConsentApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // The API is tested on its own records, not on whatever a seeded database contains.
        DB::table('tracking_scripts')->delete();
        DB::table('cookie_settings')->delete();
        DB::table('consent_records')->delete();
    }

    public function test_the_payload_offers_no_category_while_the_site_uses_only_necessary_cookies(): void
    {
        $this->script('Relace prohlížení', TrackingCategory::Necessary, enabled: true);

        $this->getJson('/api/compliance/cookie-config')
            ->assertOk()
            ->assertJsonPath('optionalCategories', [])
            ->assertJsonPath('requiresCookieConsent', false)
            ->assertJsonPath('settings.enabled', true);
    }

    public function test_an_enabled_optional_script_makes_its_category_configurable(): void
    {
        $this->script('Relace prohlížení', TrackingCategory::Necessary, enabled: true);
        $this->script('Měřicí nástroj', TrackingCategory::Analytics, enabled: true);

        $this->getJson('/api/compliance/cookie-config')
            ->assertOk()
            ->assertJsonPath('optionalCategories', ['analytics'])
            ->assertJsonPath('requiresCookieConsent', true)
            // An optional script always requires consent, even when it was created without the flag.
            ->assertJsonPath('trackingScripts.1.requiresConsent', true);
    }

    public function test_a_disabled_analytics_script_is_not_offered_nor_published(): void
    {
        $this->script('Měřicí nástroj', TrackingCategory::Analytics, enabled: false);

        $this->getJson('/api/compliance/cookie-config')
            ->assertOk()
            ->assertJsonPath('optionalCategories', [])
            ->assertJsonCount(0, 'trackingScripts');
    }

    public function test_the_payload_links_both_legal_pages(): void
    {
        $this->getJson('/api/compliance/cookie-config')
            ->assertOk()
            ->assertJsonPath('legalLinks.privacy_policy.url', route('front.gdpr'))
            ->assertJsonPath('legalLinks.cookie_policy.url', route('front.cookies'));
    }

    public function test_a_granted_category_is_stored_as_accepted_evidence(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/compliance/consent', [
            'consent_uuid' => $uuid,
            'type' => ConsentType::Cookie->value,
            'version' => '1.0',
            'preferences' => [
                'necessary' => true,
                'analytics' => true,
                'marketing' => false,
                'preferences' => false,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('stored', true);

        $record = ConsentRecord::query()->where('consent_uuid', $uuid)->firstOrFail();

        $this->assertSame(ConsentType::Cookie, $record->type);
        $this->assertTrue($record->isAccepted());
        $this->assertSame(['analytics'], $record->grantedCookieCategories());
        $this->assertNotNull($record->ip_hash);
        $this->assertNotSame('127.0.0.1', $record->ip_hash);
    }

    public function test_refusing_every_optional_category_is_stored_as_a_refusal(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/compliance/consent', [
            'consent_uuid' => $uuid,
            'type' => ConsentType::Cookie->value,
            'version' => '1.0',
            'preferences' => ['necessary' => true],
        ])->assertOk();

        $record = ConsentRecord::query()->where('consent_uuid', $uuid)->firstOrFail();

        $this->assertFalse($record->isAccepted());
        $this->assertNotNull($record->rejected_at);
        $this->assertSame([], $record->grantedCookieCategories());
    }

    public function test_the_same_browser_uuid_updates_one_record_instead_of_piling_up_rows(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/compliance/consent', [
            'consent_uuid' => $uuid,
            'type' => ConsentType::Cookie->value,
            'version' => '1.0',
            'preferences' => ['necessary' => true, 'analytics' => true],
        ])->assertOk();

        $this->postJson('/api/compliance/consent', [
            'consent_uuid' => $uuid,
            'type' => ConsentType::Cookie->value,
            'version' => '1.0',
            'preferences' => ['necessary' => true],
            'withdrawal' => true,
        ])->assertOk();

        $this->assertSame(1, ConsentRecord::query()->where('consent_uuid', $uuid)->count());

        $record = ConsentRecord::query()->where('consent_uuid', $uuid)->firstOrFail();
        $this->assertNotNull($record->withdrawn_at);
        $this->assertNull($record->accepted_at);
    }

    public function test_the_endpoint_refuses_a_gdpr_acknowledgement(): void
    {
        // A GDPR acknowledgement belongs to a submitted form and is written by the server that accepted
        // it, so the public endpoint must not create one for somebody else.
        $this->postJson('/api/compliance/consent', [
            'consent_uuid' => (string) Str::uuid(),
            'type' => ConsentType::Gdpr->value,
            'preferences' => ['necessary' => true],
        ])->assertStatus(422);

        $this->assertSame(0, ConsentRecord::query()->count());
    }

    public function test_the_necessary_category_cannot_be_refused(): void
    {
        $this->postJson('/api/compliance/consent', [
            'consent_uuid' => (string) Str::uuid(),
            'type' => ConsentType::Cookie->value,
            'preferences' => ['necessary' => false],
        ])->assertStatus(422);
    }

    private function script(string $name, TrackingCategory $category, bool $enabled): TrackingScript
    {
        return TrackingScript::query()->create([
            'name' => $name,
            'category' => $category,
            'enabled' => $enabled,
            'requires_consent' => $category->isOptional(),
            'priority' => 10,
        ]);
    }
}
