<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactSettings;
use App\Models\Contact;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The ARES import exists only as a Filament action that prefills the contact form state. Every test stubs the ARES
 * endpoint with Http::fake, so no real network call is made.
 */
class ContactSettingsAresImportTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty';

    private const REGISTRATION_NUMBER = '28288505';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Filament::setCurrentPanel('admin');

        $superAdminRole = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $admin = User::factory()->create();
        $admin->assignRole($superAdminRole);

        $this->actingAs($admin->fresh());
    }

    public function test_the_ares_action_prefills_only_the_fields_returned_by_ares(): void
    {
        $contact = $this->manualContact();

        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload())]);

        Livewire::test(ContactSettings::class)
            ->callAction('loadFromAres', ['ico' => self::REGISTRATION_NUMBER])
            ->assertNotified('Údaje z ARES byly načteny')
            ->assertSet('data.company_name', 'Stolařství Jun a synové s.r.o.')
            ->assertSet('data.registration_number', '28288505')
            ->assertSet('data.vat_number', 'CZ28288505')
            ->assertSet('data.street', 'Stará osada 540')
            ->assertSet('data.city', '664 84 Zastávka')
            ->assertSet('data.contact_person', 'Jana Nováková')
            ->assertSet('data.email', 'manual@example.com')
            ->assertSet('data.phone', '+420 111 222 333')
            ->assertSet('data.data_box', 'manualni')
            ->assertSet('data.bank_account', '123456/0100')
            ->assertSet('data.registry_note', 'Ručně udržovaná poznámka.')
            ->assertSet('data.google_maps_url', 'https://www.google.com/maps/manualni')
            ->assertSet('data.waze_url', 'https://www.waze.com/ul?manualni')
            ->assertSet('data.seznam_maps_url', 'https://mapy.cz/manualni');

        // The import only prefills the form state; the stored record stays untouched until "Uložit" is submitted.
        $contact->refresh();

        $this->assertSame('Ruční název firmy', $contact->company_name);
        $this->assertSame('Ruční ulice 1', $contact->street);
        $this->assertSame('110 00 Ruční město', $contact->city);
        $this->assertSame('CZ99999999', $contact->vat_number);
        $this->assertSame('Jana Nováková', $contact->contact_person);
    }

    public function test_the_ares_action_keeps_the_manual_values_that_ares_does_not_return(): void
    {
        $this->manualContact();

        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload([
            'dic' => null,
            'sidlo' => null,
        ]))]);

        Livewire::test(ContactSettings::class)
            ->callAction('loadFromAres', ['ico' => self::REGISTRATION_NUMBER])
            ->assertNotified('Údaje z ARES byly načteny')
            ->assertSet('data.company_name', 'Stolařství Jun a synové s.r.o.')
            ->assertSet('data.vat_number', 'CZ99999999')
            ->assertSet('data.street', 'Ruční ulice 1')
            ->assertSet('data.city', '110 00 Ruční město')
            ->assertSet('data.contact_person', 'Jana Nováková')
            ->assertSet('data.data_box', 'manualni');
    }

    public function test_the_ares_action_rejects_an_invalid_registration_number_without_a_request(): void
    {
        $this->manualContact();

        Http::fake();

        Livewire::test(ContactSettings::class)
            // The modal accepts eight digits, the check digit is rejected by the service.
            ->callAction('loadFromAres', ['ico' => '28288504'])
            ->assertNotified('Údaje z ARES se nepodařilo načíst')
            ->assertSet('data.company_name', 'Ruční název firmy')
            ->assertSet('data.street', 'Ruční ulice 1');

        Http::assertNothingSent();
    }

    public function test_the_ares_action_reports_a_missing_subject_and_keeps_the_form_state(): void
    {
        $this->manualContact();

        Http::fake([$this->fakeUrl() => Http::response(['kod' => 'NENALEZENO', 'popis' => 'Subjekt nenalezen.'], 404)]);

        Livewire::test(ContactSettings::class)
            ->callAction('loadFromAres', ['ico' => self::REGISTRATION_NUMBER])
            ->assertNotified('Údaje z ARES se nepodařilo načíst')
            ->assertSet('data.company_name', 'Ruční název firmy')
            ->assertSet('data.street', 'Ruční ulice 1');

        Http::assertSentCount(1);
    }

    public function test_the_ares_action_reports_a_server_error_and_keeps_the_form_state(): void
    {
        $this->manualContact();

        Http::fake([$this->fakeUrl() => Http::response('Server Error', 503)]);

        Livewire::test(ContactSettings::class)
            ->callAction('loadFromAres', ['ico' => self::REGISTRATION_NUMBER])
            ->assertNotified('Údaje z ARES se nepodařilo načíst')
            ->assertSet('data.company_name', 'Ruční název firmy')
            ->assertSet('data.street', 'Ruční ulice 1');

        // One initial attempt plus the two configured retries.
        Http::assertSentCount(3);
    }

    public function test_the_ares_action_refuses_a_response_that_belongs_to_another_subject(): void
    {
        $this->manualContact();

        Http::fake([$this->fakeUrl() => Http::response($this->aresPayload(['ico' => '12345679']))]);

        Livewire::test(ContactSettings::class)
            ->callAction('loadFromAres', ['ico' => self::REGISTRATION_NUMBER])
            ->assertNotified('Údaje z ARES se nepodařilo načíst')
            ->assertSet('data.company_name', 'Ruční název firmy')
            ->assertSet('data.street', 'Ruční ulice 1');
    }

    public function test_the_ares_import_is_guarded_by_the_page_access_check(): void
    {
        $component = Livewire::test(ContactSettings::class)->instance();

        $this->actingAs(User::factory()->create());

        $this->expectException(HttpException::class);

        $component->importFromAres(self::REGISTRATION_NUMBER);
    }

    /**
     * Fill the singleton record with values that differ from the ARES stub, so the form state proves which values
     * were replaced and which were preserved.
     */
    private function manualContact(): Contact
    {
        $contact = Contact::current();

        $contact->update([
            'company_name' => 'Ruční název firmy',
            'street' => 'Ruční ulice 1',
            'city' => '110 00 Ruční město',
            'contact_person' => 'Jana Nováková',
            'email' => 'manual@example.com',
            'phone' => '+420 111 222 333',
            'registration_number' => '99999999',
            'vat_number' => 'CZ99999999',
            'data_box' => 'manualni',
            'bank_account' => '123456/0100',
            'registry_note' => 'Ručně udržovaná poznámka.',
            'google_maps_url' => 'https://www.google.com/maps/manualni',
            'waze_url' => 'https://www.waze.com/ul?manualni',
            'seznam_maps_url' => 'https://mapy.cz/manualni',
        ]);

        return $contact;
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
            'sidlo' => [
                'nazevUlice' => 'Stará osada',
                'cisloDomovni' => 540,
                'nazevObce' => 'Zastávka',
                'psc' => 66484,
                'textovaAdresa' => 'Stará osada 540, 66484 Zastávka',
                'kodStatu' => 'CZ',
            ],
        ], $overrides);
    }
}
