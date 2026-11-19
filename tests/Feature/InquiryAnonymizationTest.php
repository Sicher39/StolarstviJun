<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\PrivacySetting;
use App\Services\Privacy\InquiryAnonymizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The retention step of the inquiry domain.
 *
 * The project has no customer model and no reservation domain, so anonymization touches inquiries
 * only: the contact data of the customer and its copy inside `crm_payload` are replaced, while the
 * quote itself (configuration and prices) stays readable.
 */
class InquiryAnonymizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('inquiries')->delete();
        DB::table('privacy_settings')->delete();
    }

    public function test_the_command_refuses_to_run_without_a_configured_retention_period(): void
    {
        $inquiry = $this->inquiry(createdAt: CarbonImmutable::now()->subYears(5));

        $this->artisan('gdpr:anonymize-inquiries', ['--force' => true])
            ->expectsOutputToContain('Doba uchovávání poptávek není nastavena')
            ->assertExitCode(1);

        $this->assertSame('Jan Novák', $inquiry->fresh()->customer_name);
        $this->assertNull($inquiry->fresh()->anonymized_at);
    }

    public function test_the_command_requires_exactly_one_explicit_mode(): void
    {
        PrivacySetting::query()->create(['inquiry_retention_months' => 12]);

        $this->artisan('gdpr:anonymize-inquiries')
            ->expectsOutputToContain('Zadejte právě jednu možnost')
            ->assertExitCode(1);
    }

    public function test_the_dry_run_reports_the_count_and_changes_nothing(): void
    {
        PrivacySetting::query()->create(['inquiry_retention_months' => 12]);

        $expired = $this->inquiry(createdAt: CarbonImmutable::now()->subMonths(18));
        $recent = $this->inquiry(createdAt: CarbonImmutable::now()->subMonths(2));

        $this->artisan('gdpr:anonymize-inquiries', ['--dry-run' => true])
            ->expectsOutputToContain('Připraveno k anonymizaci: 1')
            ->assertExitCode(0);

        $this->assertSame('Jan Novák', $expired->fresh()->customer_name);
        $this->assertNull($expired->fresh()->anonymized_at);
        $this->assertSame('Jan Novák', $recent->fresh()->customer_name);
    }

    public function test_only_expired_inquiries_are_anonymized_and_the_quote_survives(): void
    {
        PrivacySetting::query()->create(['inquiry_retention_months' => 12]);

        $expired = $this->inquiry(createdAt: CarbonImmutable::now()->subMonths(18));
        $recent = $this->inquiry(createdAt: CarbonImmutable::now()->subMonths(2));

        $this->artisan('gdpr:anonymize-inquiries', ['--force' => true])
            ->expectsOutputToContain('Anonymizováno: 1')
            ->assertExitCode(0);

        $anonymized = $expired->fresh();

        $this->assertSame(InquiryAnonymizer::REDACTED_NAME, $anonymized->customer_name);
        $this->assertSame(InquiryAnonymizer::REDACTED_EMAIL, $anonymized->customer_email);
        $this->assertNull($anonymized->customer_phone);
        $this->assertNull($anonymized->customer_message);
        $this->assertNotNull($anonymized->anonymized_at);
        $this->assertTrue($anonymized->isAnonymized());

        // The copy of the personal data inside the CRM payload is redacted as well.
        $this->assertSame(InquiryAnonymizer::REDACTED_EMAIL, $anonymized->crm_payload['customer']['email']);
        $this->assertNull($anonymized->crm_payload['customer']['phone']);
        $this->assertSame('front.configurator', $anonymized->crm_payload['submitted_from']);

        // The quote itself is not personal data and stays readable.
        $this->assertSame(['door_model' => ['name' => 'Model X']], $anonymized->configuration);
        $this->assertSame('10000.00', $anonymized->price_without_vat);

        // An inquiry inside the retention window stays untouched.
        $this->assertSame('Jan Novák', $recent->fresh()->customer_name);
        $this->assertNull($recent->fresh()->anonymized_at);
    }

    public function test_a_second_run_does_not_anonymize_the_same_inquiry_again(): void
    {
        PrivacySetting::query()->create(['inquiry_retention_months' => 12]);

        $this->inquiry(createdAt: CarbonImmutable::now()->subMonths(18));

        $this->artisan('gdpr:anonymize-inquiries', ['--force' => true])->assertExitCode(0);

        $anonymizedAt = Inquiry::query()->firstOrFail()->anonymized_at?->toDateTimeString();

        $this->travel(1)->days();

        $this->artisan('gdpr:anonymize-inquiries', ['--force' => true])
            ->expectsOutputToContain('Anonymizováno: 0')
            ->assertExitCode(0);

        $this->assertSame($anonymizedAt, Inquiry::query()->firstOrFail()->anonymized_at?->toDateTimeString());
    }

    public function test_the_period_can_be_given_as_an_option_without_changing_the_setting(): void
    {
        $inquiry = $this->inquiry(createdAt: CarbonImmutable::now()->subMonths(8));

        $this->artisan('gdpr:anonymize-inquiries', ['--force' => true, '--months' => 6])
            ->expectsOutputToContain('Anonymizováno: 1')
            ->assertExitCode(0);

        $this->assertNotNull($inquiry->fresh()->anonymized_at);
        $this->assertNull(PrivacySetting::configuredRetentionMonths());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function inquiry(?CarbonImmutable $createdAt = null, array $overrides = []): Inquiry
    {
        $inquiry = Inquiry::query()->create([
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan.novak@example.test',
            'customer_phone' => '+420 111 222 333',
            'customer_message' => 'Mám zájem o dveře na míru.',
            'price_without_vat' => 10000,
            'price_with_vat' => 12100,
            'configuration' => ['door_model' => ['name' => 'Model X']],
            'crm_payload' => [
                'customer' => [
                    'name' => 'Jan Novák',
                    'email' => 'jan.novak@example.test',
                    'phone' => '+420 111 222 333',
                    'message' => 'Mám zájem o dveře na míru.',
                ],
                'configuration' => ['door_model' => ['name' => 'Model X']],
                'submitted_from' => 'front.configurator',
            ],
            'status' => 'new',
            ...$overrides,
        ]);

        if ($createdAt instanceof CarbonImmutable) {
            $inquiry->created_at = $createdAt;
            $inquiry->save();
        }

        return $inquiry;
    }
}
