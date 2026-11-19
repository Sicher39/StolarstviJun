<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactSettings;
use App\Models\Contact;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests render the Inertia root view, so Vite must not require a production build manifest.
        $hotFile = storage_path('framework/testing/vite-hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://127.0.0.1:5173');

        Vite::useHotFile($hotFile);
    }

    public function test_contact_page_receives_the_editable_contact_record(): void
    {
        $contact = Contact::current();
        $contact->update([
            'contact_person' => 'Jana Nováková',
            'phone' => '+420 111 222 333',
        ]);

        $response = $this->get(route('front.contact'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page): Assert => $page
            // The frontend pages live outside Inertia's default page path, so the file check is disabled here.
            ->component('Contact', false)
            ->where('contact.company_name', $contact->company_name)
            ->where('contact.street', $contact->street)
            ->where('contact.city', $contact->city)
            ->where('contact.contact_person', 'Jana Nováková')
            ->where('contact.phone', '+420 111 222 333')
            ->where('contact.bank_account', $contact->bank_account)
            ->where('contact.google_maps_url', $contact->google_maps_url)
            ->where('contact.waze_url', $contact->waze_url)
            ->where('contact.seznam_maps_url', $contact->seznam_maps_url)
        );
    }

    public function test_contact_page_never_multiplies_the_singleton_record(): void
    {
        Contact::query()->delete();

        Contact::current();
        Contact::current();

        $this->get(route('front.contact'))->assertOk();

        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_contact_record_is_created_with_the_published_values(): void
    {
        Contact::query()->delete();

        $contact = Contact::current();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertSame('Stolařství Jun a synové s.r.o.', $contact->company_name);
        $this->assertSame('Stará Osada 540', $contact->street);
        $this->assertSame('664 84 Zastávka u Brna', $contact->city);
        $this->assertSame('Miroslav Jun', $contact->contact_person);
        $this->assertSame('info@drevovyroba-jun.cz', $contact->email);
        $this->assertSame('+420 777 123 123', $contact->phone);
        $this->assertSame('28288505', $contact->registration_number);
        $this->assertSame('CZ28288505', $contact->vat_number);
        $this->assertSame('2bc4vfu', $contact->data_box);
        $this->assertSame('xxxxxxxxx/xxxx', $contact->bank_account);
        $this->assertSame('Zapsána pod značkou C 58837/KSBR Krajským soudem v Brně.', $contact->registry_note);
    }

    public function test_contact_settings_page_is_not_accessible_without_the_shield_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(ContactSettings::canAccess());
    }

    public function test_contact_settings_page_is_accessible_with_the_shield_permission(): void
    {
        $permission = Permission::findOrCreate('View:ContactSettings', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user->fresh());

        $this->assertTrue(ContactSettings::canAccess());
    }

    public function test_contact_settings_page_is_accessible_for_the_shield_super_admin_role(): void
    {
        $role = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user->fresh());

        $this->assertTrue(ContactSettings::canAccess());
    }
}
