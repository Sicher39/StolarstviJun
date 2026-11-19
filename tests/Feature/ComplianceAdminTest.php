<?php

namespace Tests\Feature;

use App\Enums\Compliance\TrackingCategory;
use App\Filament\Resources\ConsentRecordResource;
use App\Filament\Resources\CookieSettingResource;
use App\Filament\Resources\DataProcessingPurposeResource;
use App\Filament\Resources\LegalDocumentResource;
use App\Filament\Resources\SeoPageResource;
use App\Filament\Resources\TrackingScriptResource;
use App\Models\ConsentRecord;
use App\Models\CookieSetting;
use App\Models\DataProcessingPurpose;
use App\Models\TrackingScript;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The GDPR area of the administration.
 *
 * The six resources the workshop works with sit in one navigation group and carry the Czech labels the
 * editors know; the panel navigation is the contract here, not an implementation detail, because the whole
 * area is only usable when the entries are found in the expected place.
 */
class ComplianceAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tracking_scripts')->delete();
        DB::table('cookie_settings')->delete();
        DB::table('data_processing_purposes')->delete();
    }

    public function test_the_gdpr_navigation_group_lists_every_resource_of_the_area(): void
    {
        $expected = [
            LegalDocumentResource::class => ['GDPR', 'Právní dokumenty', 10],
            DataProcessingPurposeResource::class => ['GDPR', 'Účely zpracování', 20],
            CookieSettingResource::class => ['GDPR', 'Cookie nastavení', 30],
            SeoPageResource::class => ['GDPR', 'SEO stránek', 40],
            TrackingScriptResource::class => ['GDPR', 'Tracking scripty', 50],
            ConsentRecordResource::class => ['GDPR', 'Záznamy souhlasů', 60],
        ];

        foreach ($expected as $resource => [$group, $label, $sort]) {
            $this->assertSame($group, $resource::getNavigationGroup(), 'Skupina u '.$resource);
            $this->assertSame($label, $resource::getNavigationLabel(), 'Popisek u '.$resource);
            $this->assertSame($sort, $resource::getNavigationSort(), 'Pořadí u '.$resource);
        }
    }

    public function test_the_cookie_settings_are_a_singleton(): void
    {
        $this->assertTrue(CookieSettingResource::canCreate());

        $setting = CookieSetting::query()->create(CookieSetting::defaults());

        $this->assertFalse(CookieSettingResource::canCreate());
        $this->assertSame($setting->id, CookieSetting::current()->id);
    }

    public function test_an_optional_script_always_requires_consent(): void
    {
        $script = TrackingScript::query()->create([
            'name' => 'Měřicí nástroj',
            'category' => TrackingCategory::Analytics,
            'enabled' => true,
            'requires_consent' => false,
            'priority' => 10,
        ]);

        // The administration must not be able to turn a tracking script into one that runs without
        // consent, so the model normalises the flag on save.
        $this->assertTrue($script->refresh()->requires_consent);
        $this->assertTrue($script->needsConsent());
    }

    public function test_a_necessary_script_needs_no_consent_and_carries_no_code(): void
    {
        $script = TrackingScript::query()->create([
            'name' => 'Relace prohlížení',
            'category' => TrackingCategory::Necessary,
            'enabled' => true,
            'requires_consent' => false,
            'priority' => 10,
        ]);

        $this->assertFalse($script->refresh()->requires_consent);
        $this->assertNull($script->code);

        $payload = $script->toFrontendArray();

        $this->assertSame('necessary', $payload['category']);
        $this->assertFalse($payload['requiresConsent']);
    }

    public function test_consent_records_are_read_only_evidence(): void
    {
        $record = new ConsentRecord();

        $this->assertFalse(ConsentRecordResource::canCreate());
        $this->assertFalse(ConsentRecordResource::canEdit($record));
        $this->assertFalse(ConsentRecordResource::canDelete($record));
    }

    public function test_the_purpose_select_offers_only_the_active_purposes_in_order(): void
    {
        DataProcessingPurpose::query()->create([
            'name' => 'Druhý účel',
            'context' => 'second',
            'is_active' => true,
            'priority' => 20,
        ]);

        DataProcessingPurpose::query()->create([
            'name' => 'První účel',
            'context' => 'first',
            'is_active' => true,
            'priority' => 10,
        ]);

        DataProcessingPurpose::query()->create([
            'name' => 'Vypnutý účel',
            'context' => 'inactive',
            'is_active' => false,
            'priority' => 5,
        ]);

        // The privacy policy publishes the active purposes in the order of `priority`; a deactivated
        // purpose is kept in the administration but no longer published.
        $this->assertSame(
            ['První účel', 'Druhý účel'],
            array_values(DataProcessingPurposeResource::activeOptions()),
        );
    }
}
