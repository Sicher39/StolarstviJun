<?php

namespace Tests\Feature;

use App\Services\Ares\AresCompanyLookup;
use App\Services\Ares\AresLookupException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The ARES service is tested exclusively against Http::fake stubs shaped like the real
 * https://ares.gov.cz/ekonomicke-subjekty-v-be payload, so no test ever calls the real API.
 */
class AresCompanyLookupTest extends TestCase
{
    private const REGISTRATION_NUMBER = '28288505';

    private const ENDPOINT = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty';

    protected function setUp(): void
    {
        parent::setUp();

        // Any request that is not explicitly faked fails the test instead of reaching the network.
        Http::preventStrayRequests();
    }

    public function test_it_returns_only_the_whitelisted_fields_returned_by_ares(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload())]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(' 28288505 ');

        $this->assertSame([
            'registration_number' => '28288505',
            'company_name' => 'Stolařství Jun a synové s.r.o.',
            'vat_number' => 'CZ28288505',
            'street' => 'Stará osada 540',
            'city' => '664 84 Zastávka',
        ], $attributes);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::ENDPOINT.'/'.self::REGISTRATION_NUMBER
            && $request->method() === 'GET');
    }

    public function test_it_composes_the_street_with_the_orientation_number_and_letter(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload([
            'sidlo' => [
                'nazevUlice' => 'Evropská',
                'cisloDomovni' => 370,
                'cisloOrientacni' => 15,
                'cisloOrientacniPismeno' => 'a',
                'nazevObce' => 'Praha',
                'psc' => 16000,
            ],
        ]))]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(self::REGISTRATION_NUMBER);

        $this->assertSame('Evropská 370/15a', $attributes['street']);
        $this->assertSame('160 00 Praha', $attributes['city']);
    }

    public function test_it_omits_the_vat_number_when_ares_does_not_return_a_dic(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload(['dic' => null]))]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(self::REGISTRATION_NUMBER);

        $this->assertArrayNotHasKey('vat_number', $attributes);
        $this->assertSame('Stolařství Jun a synové s.r.o.', $attributes['company_name']);
    }

    public function test_it_omits_the_vat_number_when_the_dic_is_empty(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload(['dic' => '   ']))]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(self::REGISTRATION_NUMBER);

        $this->assertArrayNotHasKey('vat_number', $attributes);
    }

    public function test_it_keeps_the_manual_address_when_ares_returns_no_seat(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload(['sidlo' => null]))]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(self::REGISTRATION_NUMBER);

        $this->assertArrayNotHasKey('street', $attributes);
        $this->assertArrayNotHasKey('city', $attributes);
    }

    public function test_it_never_composes_an_incomplete_street_or_city(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload([
            'sidlo' => [
                'nazevUlice' => 'Stará osada',
                'cisloDomovni' => null,
                'psc' => null,
                'nazevObce' => 'Zastávka',
            ],
        ]))]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(self::REGISTRATION_NUMBER);

        $this->assertArrayNotHasKey('street', $attributes);
        $this->assertArrayNotHasKey('city', $attributes);
    }

    public function test_it_skips_a_value_that_does_not_fit_the_column(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload([
            'obchodniJmeno' => str_repeat('A', 256),
        ]))]);

        $attributes = app(AresCompanyLookup::class)->forRegistrationNumber(self::REGISTRATION_NUMBER);

        $this->assertArrayNotHasKey('company_name', $attributes);
        $this->assertSame('28288505', $attributes['registration_number']);
    }

    public function test_it_rejects_invalid_registration_numbers_without_any_request(): void
    {
        Http::fake();

        foreach (['', '1234567', '123456789', 'CZ28288505', '2828850a', '28288504'] as $invalidRegistrationNumber) {
            $exception = $this->captureFailure($invalidRegistrationNumber);

            $this->assertSame(
                AresLookupException::REASON_INVALID_REGISTRATION_NUMBER,
                $exception->reason,
                "The IČ [{$invalidRegistrationNumber}] was expected to be rejected.",
            );
        }

        Http::assertNothingSent();
    }

    public function test_it_reports_a_missing_subject_and_does_not_retry(): void
    {
        Http::fake([$this->fakeUrl() => Http::response(['kod' => 'NENALEZENO'], 404)]);

        $exception = $this->captureFailure(self::REGISTRATION_NUMBER);

        $this->assertSame(AresLookupException::REASON_NOT_FOUND, $exception->reason);
        // The Czech message is built locally, it never contains the upstream payload.
        $this->assertSame('Subjekt s tímto IČ nebyl v ARES nalezen.', $exception->getMessage());
        Http::assertSentCount(1);
    }

    public function test_it_reports_other_client_errors_as_unavailable_without_retrying(): void
    {
        Http::fake([$this->fakeUrl() => Http::response(['kod' => 'CHYBA'], 400)]);

        $exception = $this->captureFailure(self::REGISTRATION_NUMBER);

        $this->assertSame(AresLookupException::REASON_UNAVAILABLE, $exception->reason);
        Http::assertSentCount(1);
    }

    public function test_it_retries_server_errors_and_then_reports_the_service_as_unavailable(): void
    {
        Http::fake([$this->fakeUrl() => Http::response('Server Error', 503)]);

        $exception = $this->captureFailure(self::REGISTRATION_NUMBER);

        $this->assertSame(AresLookupException::REASON_UNAVAILABLE, $exception->reason);
        // One initial attempt plus the two configured retries.
        Http::assertSentCount(3);
    }

    public function test_it_reports_a_network_failure_as_unavailable_service(): void
    {
        Http::fake([$this->fakeUrl() => Http::failedConnection()]);

        $exception = $this->captureFailure(self::REGISTRATION_NUMBER);

        $this->assertSame(AresLookupException::REASON_UNAVAILABLE, $exception->reason);
    }

    public function test_it_reports_an_unexpected_response_for_a_non_json_body(): void
    {
        Http::fake([$this->fakeUrl() => Http::response('<html>Údržba</html>', 200, ['Content-Type' => 'text/html'])]);

        $exception = $this->captureFailure(self::REGISTRATION_NUMBER);

        $this->assertSame(AresLookupException::REASON_INVALID_RESPONSE, $exception->reason);
    }

    public function test_it_refuses_a_response_that_belongs_to_another_subject(): void
    {
        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload(['ico' => '12345679']))]);

        $exception = $this->captureFailure(self::REGISTRATION_NUMBER);

        $this->assertSame(AresLookupException::REASON_REGISTRATION_NUMBER_MISMATCH, $exception->reason);
    }

    private function captureFailure(string $registrationNumber): AresLookupException
    {
        try {
            app(AresCompanyLookup::class)->forRegistrationNumber($registrationNumber);
        } catch (AresLookupException $exception) {
            return $exception;
        }

        $this->fail("The ARES lookup for the IČ [{$registrationNumber}] was expected to fail.");
    }

    private function fakeUrl(): string
    {
        return self::ENDPOINT.'/*';
    }

    /**
     * Subset of a real ARES subject document, with the keys the mapping relies on.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function aresPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'ico' => self::REGISTRATION_NUMBER,
            'icoId' => self::REGISTRATION_NUMBER,
            'obchodniJmeno' => 'Stolařství Jun a synové s.r.o.',
            'dic' => 'CZ28288505',
            'pravniForma' => '112',
            'czNace' => ['16230'],
            'seznamRegistraci' => ['stavZdrojeDph' => 'AKTIVNI'],
            'adresaDorucovaci' => [
                'radekAdresy1' => 'Stará osada 540',
                'radekAdresy2' => '66484 Zastávka',
            ],
            'sidlo' => [
                'kodStatu' => 'CZ',
                'nazevKraje' => 'Jihomoravský kraj',
                'nazevUlice' => 'Stará osada',
                'cisloDomovni' => 540,
                'nazevCastiObce' => 'Zastávka',
                'nazevObce' => 'Zastávka',
                'psc' => 66484,
                'textovaAdresa' => 'Stará osada 540, 66484 Zastávka',
            ],
        ], $overrides);
    }
}
