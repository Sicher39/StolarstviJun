<?php

namespace Tests\Feature;

use App\Filament\Pages\PrivacySettings;
use App\Models\PrivacySetting;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The retention setting is the only privacy decision kept outside the legal text. It is nullable on
 * purpose: an unset value keeps the placeholder in the published text and blocks the anonymization
 * command, so the code never invents a retention period.
 */
class PrivacySettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        DB::table('privacy_settings')->delete();
    }

    public function test_the_setting_is_null_until_the_owner_decides(): void
    {
        $this->assertNull(PrivacySetting::configuredRetentionMonths());
        $this->assertSame(0, PrivacySetting::query()->count());
    }

    public function test_the_page_is_not_accessible_without_the_shield_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(PrivacySettings::canAccess());
    }

    public function test_the_page_is_accessible_with_the_shield_permission(): void
    {
        $permission = Permission::findOrCreate('View:PrivacySettings', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user->fresh());

        $this->assertTrue(PrivacySettings::canAccess());
    }

    public function test_the_page_is_accessible_for_the_shield_super_admin_role(): void
    {
        $role = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user->fresh());

        $this->assertTrue(PrivacySettings::canAccess());
    }

    public function test_saving_the_period_stores_it_in_the_singleton_record(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(PrivacySettings::class)
            ->assertSet('data.inquiry_retention_months', null)
            ->set('data.inquiry_retention_months', '24')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(24, PrivacySetting::configuredRetentionMonths());
        $this->assertSame(1, PrivacySetting::query()->count());
    }

    public function test_the_stored_period_is_filled_back_into_the_form(): void
    {
        PrivacySetting::query()->create(['inquiry_retention_months' => 36]);

        $this->actingAsSuperAdmin();

        Livewire::test(PrivacySettings::class)
            ->assertSet('data.inquiry_retention_months', 36);
    }

    public function test_saving_is_guarded_by_the_page_access_check(): void
    {
        $this->actingAsSuperAdmin();

        $component = Livewire::test(PrivacySettings::class)->instance();

        $this->actingAs(User::factory()->create());

        $this->expectException(HttpException::class);

        $component->save();
    }

    private function actingAsSuperAdmin(): User
    {
        $role = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin->fresh());

        return $admin;
    }
}
