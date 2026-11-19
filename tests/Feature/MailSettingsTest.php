<?php

namespace Tests\Feature;

use App\Filament\Pages\MailSettings;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mail\MailNotConfiguredException;
use App\Services\Mail\MailSettingsMailer;
use App\Services\Mail\SmtpConnectionTester;
use App\Services\Mail\SmtpTestResult;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;
use Throwable;

/**
 * The mailbox maintained in the admin panel.
 *
 * The credentials live in the database only (never in `.env`), are encrypted at rest, never reach a browser and are never
 * wiped by saving the form with an empty password field. The outgoing server of a complete mailbox becomes the mail
 * configuration of the application, so the office can change it without a deployment.
 *
 * The addresses, the servers, both user names and both passwords are stored without the surrounding whitespace, because
 * a trailing space is invisible in the form but makes the SMTP login fail. Only the edges of a secret are trimmed - a
 * space inside a password is a part of it.
 *
 * The receiving (POP3) side is stored for the office only: nothing in the application opens a POP3 session yet.
 */
class MailSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        DB::table('mail_settings')->delete();
    }

    public function test_the_credentials_are_encrypted_at_rest_and_hidden_from_array_output(): void
    {
        $settings = $this->mailbox();

        $stored = DB::table('mail_settings')->where('id', $settings->getKey())->first();

        $this->assertNotNull($stored);
        $this->assertNotSame('smtp-secret', $stored->smtp_password);
        $this->assertNotSame('pop3-secret', $stored->pop3_password);
        $this->assertNotSame('smtp-user', $stored->smtp_username);

        $fresh = $settings->fresh();

        $this->assertSame('smtp-secret', $fresh?->smtp_password);
        $this->assertSame('pop3-secret', $fresh?->pop3_password);
        $this->assertSame('smtp-user', $fresh?->smtp_username);

        $attributes = $settings->toArray();

        $this->assertArrayNotHasKey('smtp_password', $attributes);
        $this->assertArrayNotHasKey('pop3_password', $attributes);
        $this->assertArrayHasKey('from_address', $attributes);
    }

    public function test_the_page_is_not_accessible_without_the_shield_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(MailSettings::canAccess());
    }

    public function test_the_page_is_accessible_with_the_shield_permission(): void
    {
        $permission = Permission::findOrCreate('View:MailSettings', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user->fresh());

        $this->assertTrue(MailSettings::canAccess());
    }

    public function test_the_page_is_accessible_for_the_shield_super_admin_role(): void
    {
        $role = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user->fresh());

        $this->assertTrue(MailSettings::canAccess());
    }

    public function test_saving_the_form_stores_the_mailbox_in_the_singleton_record(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->assertSet('data.enabled', false)
            ->set('data.enabled', true)
            ->set('data.from_address', 'info@drevovyroba-jun.cz')
            ->set('data.from_name', 'Stolařství Jun')
            ->set('data.smtp_host', 'smtp.example.com')
            ->set('data.smtp_port', '587')
            ->set('data.smtp_encryption', MailSetting::ENCRYPTION_TLS)
            ->set('data.smtp_username', 'info@drevovyroba-jun.cz')
            ->set('data.smtp_password', 'smtp-secret')
            ->set('data.pop3_host', 'pop3.example.com')
            ->set('data.pop3_port', '995')
            ->set('data.pop3_encryption', MailSetting::ENCRYPTION_SSL)
            ->set('data.pop3_username', 'info@drevovyroba-jun.cz')
            ->set('data.pop3_password', 'pop3-secret')
            ->call('save')
            ->assertHasNoErrors();

        $settings = MailSetting::configured();

        $this->assertNotNull($settings);
        $this->assertTrue($settings->enabled);
        $this->assertSame('info@drevovyroba-jun.cz', $settings->from_address);
        $this->assertSame('Stolařství Jun', $settings->from_name);
        $this->assertSame('smtp.example.com', $settings->smtp_host);
        $this->assertSame(587, $settings->smtp_port);
        $this->assertSame('smtp-secret', $settings->smtp_password);
        $this->assertSame('pop3.example.com', $settings->pop3_host);
        $this->assertSame(995, $settings->pop3_port);
        $this->assertSame('pop3-secret', $settings->pop3_password);
        $this->assertSame(1, MailSetting::query()->count());
    }

    public function test_saving_without_a_password_keeps_the_stored_secret(): void
    {
        $settings = $this->mailbox();

        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            // The password field starts empty and is left empty on purpose.
            ->set('data.smtp_host', 'smtp.novy.test')
            ->set('data.smtp_password', '')
            ->set('data.pop3_password', '')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $settings->fresh();

        $this->assertSame('smtp.novy.test', $fresh?->smtp_host);
        $this->assertSame('smtp-secret', $fresh?->smtp_password);
        $this->assertSame('pop3-secret', $fresh?->pop3_password);
    }

    public function test_a_password_field_of_whitespace_only_keeps_the_stored_secret(): void
    {
        $settings = $this->mailbox();

        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('data.smtp_password', '   ')
            ->set('data.pop3_password', "\u{00A0}")
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $settings->fresh();

        // Whitespace only is blank, so the field is not written at all and the secrets stay untouched - including the
        // invisible space, which a plain `filled()` check would have let through.
        $this->assertSame('smtp-secret', $fresh?->smtp_password);
        $this->assertSame('pop3-secret', $fresh?->pop3_password);
    }

    public function test_a_password_typed_with_a_surrounding_space_is_stored_without_it(): void
    {
        $settings = $this->mailbox();

        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('data.smtp_password', '  smtp secret  ')
            ->call('save')
            ->assertHasNoErrors();

        // The pasted space around the secret is gone, the one inside it stays: it is a part of the password.
        $this->assertSame('smtp secret', $settings->fresh()?->smtp_password);
    }

    public function test_the_stored_values_are_trimmed_at_their_edges(): void
    {
        // A row written before the trim existed: the model events are muted, so the spaces survive the insert.
        $settings = MailSetting::withoutEvents(static fn (): MailSetting => MailSetting::query()->create([
            'enabled' => true,
            'from_address' => ' info@drevovyroba-jun.cz ',
            'from_name' => ' Stolařství Jun ',
            'smtp_host' => "smtp.example.com\n",
            'smtp_port' => 587,
            'smtp_encryption' => MailSetting::ENCRYPTION_TLS,
            'smtp_username' => 'info@example.com ',
            'smtp_password' => "\u{00A0}smtp secret ",
            'pop3_host' => ' pop3.example.com',
            'pop3_username' => "pop3-user\u{00A0}",
            'pop3_password' => ' pop3-secret ',
        ]));

        $fresh = $settings->fresh();

        $this->assertSame('info@drevovyroba-jun.cz ', $fresh?->from_address);

        // The row is healed by its next save, and the mailer trims it again so it works even before that save.
        $configuration = app(MailSettingsMailer::class)->configuration($settings);

        $this->assertSame('smtp.example.com', $configuration['host']);
        $this->assertSame('info@example.com', $configuration['username']);
        $this->assertSame('smtp secret', $configuration['password']);

        $settings->update(['pop3_port' => 995]);

        $healed = $settings->fresh();

        $this->assertSame('info@drevovyroba-jun.cz', $healed?->from_address);
        $this->assertSame('Stolařství Jun', $healed?->from_name);
        $this->assertSame('smtp.example.com', $healed?->smtp_host);
        $this->assertSame('info@example.com', $healed?->smtp_username);
        $this->assertSame('smtp secret', $healed?->smtp_password);
        $this->assertSame('pop3.example.com', $healed?->pop3_host);
        $this->assertSame('pop3-user', $healed?->pop3_username);
        $this->assertSame('pop3-secret', $healed?->pop3_password);
    }

    public function test_an_incomplete_mailbox_is_not_sendable(): void
    {
        $settings = $this->mailbox();

        $this->assertTrue($settings->canSendMail());
        $this->assertTrue($settings->canReceiveMail());

        $settings->enabled = false;
        $this->assertFalse($settings->canSendMail());
        $this->assertFalse($settings->canReceiveMail());

        $settings->enabled = true;
        $settings->from_address = null;
        $this->assertFalse($settings->canSendMail());

        // A value of invisible characters only is not a configured sender either, so it never becomes a `From` header.
        $settings->from_address = "  \u{00A0}";
        $this->assertFalse($settings->canSendMail());

        // A secret of spaces only is not a credential either: the trim would remove all of it before the login.
        $settings->from_address = 'info@drevovyroba-jun.cz';
        $settings->smtp_password = "  \u{00A0}";
        $this->assertFalse($settings->canSendMail());

        $settings->pop3_password = null;
        $this->assertFalse($settings->canReceiveMail());
    }

    public function test_the_stored_mailbox_becomes_the_mail_configuration(): void
    {
        $this->mailbox();

        $this->assertTrue(app(MailSettingsMailer::class)->register(app('mail.manager')));

        // The complete mailbox replaces the mailer of the environment, so no credential has to be part of .env.
        $this->assertSame(MailSettingsMailer::NAME, config('mail.default'));
        $this->assertSame('smtp', config('mail.mailers.mailbox.transport'));
        $this->assertSame('smtp', config('mail.mailers.mailbox.scheme'));
        $this->assertSame('smtp.example.com', config('mail.mailers.mailbox.host'));
        $this->assertSame(587, config('mail.mailers.mailbox.port'));
        $this->assertSame('smtp-user', config('mail.mailers.mailbox.username'));
        $this->assertSame('smtp-secret', config('mail.mailers.mailbox.password'));
        $this->assertTrue(config('mail.mailers.mailbox.require_tls'));
        $this->assertSame('info@drevovyroba-jun.cz', config('mail.from.address'));
        $this->assertSame('Stolařství Jun', config('mail.from.name'));
    }

    public function test_an_empty_or_switched_off_mailbox_leaves_the_mail_configuration_untouched(): void
    {
        $configuredMailer = config('mail.default');

        // The mailer of the environment stays in place, which is what a fresh installation relies on.
        $this->assertFalse(app(MailSettingsMailer::class)->register(app('mail.manager')));
        $this->assertSame($configuredMailer, config('mail.default'));
        $this->assertNull(config('mail.mailers.mailbox'));

        $settings = $this->mailbox(['enabled' => false]);

        $this->assertFalse(app(MailSettingsMailer::class)->register(app('mail.manager')));
        $this->assertSame($configuredMailer, config('mail.default'));
        $this->assertNull(config('mail.mailers.mailbox'));

        // A switched-off mailbox cannot be sent with explicitly either.
        $this->expectException(MailNotConfiguredException::class);

        app(MailSettingsMailer::class)->for($settings);
    }

    public function test_saving_is_guarded_by_the_page_access_check(): void
    {
        $this->actingAsSuperAdmin();

        $component = Livewire::test(MailSettings::class)->instance();

        $this->actingAs(User::factory()->create());

        $this->expectException(HttpException::class);

        $component->save();
    }

    public function test_the_smtp_test_uses_the_values_typed_in_the_form(): void
    {
        $settings = $this->mailbox();
        $tester = $this->scriptedTester();

        Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class)
            ->set('data.smtp_host', ' smtp.novy.test ')
            ->set('data.smtp_username', 'novy@smtp.test')
            ->set('data.smtp_password', '  nove heslo  ')
            ->callAction('testSmtpConnection')
            ->assertNotified(SmtpTestResult::Authenticated->title());

        // The session was opened with the unsaved values of the form, without the pasted spaces.
        $this->assertSame('smtp.novy.test', $tester->testedSettings?->smtp_host);
        $this->assertSame('novy@smtp.test', $tester->handshaken?->getUsername());
        $this->assertSame('nove heslo', $tester->handshaken?->getPassword());

        // Not a single value of the row changed, so the test is a question and not a save.
        $fresh = $settings->fresh();

        $this->assertSame('smtp.example.com', $fresh?->smtp_host);
        $this->assertSame('smtp-user', $fresh?->smtp_username);
        $this->assertSame('smtp-secret', $fresh?->smtp_password);
    }

    public function test_the_smtp_test_uses_the_stored_password_when_the_field_is_left_empty(): void
    {
        $this->mailbox();
        $tester = $this->scriptedTester();

        Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class)
            // The field starts empty and is left empty on purpose, exactly like the write-only rule of the save.
            ->set('data.smtp_password', '')
            ->callAction('testSmtpConnection')
            ->assertNotified(SmtpTestResult::Authenticated->title());

        $this->assertSame('smtp-secret', $tester->handshaken?->getPassword());
        $this->assertSame('smtp-user', $tester->handshaken?->getUsername());
    }

    public function test_the_smtp_test_reports_refused_credentials_without_repeating_them(): void
    {
        $this->mailbox();
        $tester = $this->scriptedTester();
        $tester->failure = new TransportException(
            'Failed to authenticate on SMTP server with username "smtp-user" using the following authenticators: "LOGIN", "PLAIN".',
            535,
        );

        Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class)
            ->callAction('testSmtpConnection')
            ->assertNotified(SmtpTestResult::InvalidCredentials->title());

        // Only the safe category is shown: neither the answer of the server nor the credentials ever are.
        $message = SmtpTestResult::InvalidCredentials->title().' '.SmtpTestResult::InvalidCredentials->body();

        $this->assertStringNotContainsString('smtp-user', $message);
        $this->assertStringNotContainsString('authenticators', $message);
        $this->assertStringNotContainsString('LOGIN', $message);
    }

    public function test_the_smtp_test_saves_nothing(): void
    {
        $settings = $this->mailbox();
        $before = (array) DB::table('mail_settings')->where('id', $settings->getKey())->first();

        $this->scriptedTester();

        Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class)
            ->set('data.smtp_password', 'nove-heslo')
            ->callAction('testSmtpConnection')
            ->assertNotified(SmtpTestResult::Authenticated->title());

        // The whole row - including `updated_at` - is exactly as it was before the test.
        $this->assertSame($before, (array) DB::table('mail_settings')->where('id', $settings->getKey())->first());
    }

    public function test_the_smtp_test_reports_missing_settings_without_opening_a_connection(): void
    {
        $this->mailbox(['smtp_password' => null]);
        $tester = $this->scriptedTester();

        Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class)
            ->callAction('testSmtpConnection')
            ->assertNotified(SmtpTestResult::NotConfigured->title());

        $this->assertSame(0, $tester->handshakes);
    }

    public function test_the_smtp_test_is_limited_per_user(): void
    {
        $this->mailbox();
        $tester = $this->scriptedTester();

        $component = Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class);

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $component->callAction('testSmtpConnection');
        }

        $this->assertSame(3, $tester->handshakes);

        $component->callAction('testSmtpConnection')
            ->assertNotified('Test SMTP spojení se spouští příliš často');

        // The refused attempt never opened a connection to the server.
        $this->assertSame(3, $tester->handshakes);
    }

    public function test_the_smtp_test_action_is_offered_to_the_office(): void
    {
        $this->mailbox();

        Livewire::actingAs($this->officeUser())
            ->test(MailSettings::class)
            ->assertOk()
            ->assertSee('Otestovat SMTP spojení');
    }

    public function test_no_smtp_test_message_can_carry_the_credentials(): void
    {
        foreach (SmtpTestResult::cases() as $result) {
            $message = $result->title().' '.$result->body();

            $this->assertStringNotContainsString('smtp-secret', $message);
            $this->assertStringNotContainsString('smtp-user', $message);
            $this->assertStringNotContainsString('example.com', $message);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mailbox(array $attributes = []): MailSetting
    {
        return MailSetting::query()->create(array_merge([
            'enabled' => true,
            'from_name' => 'Stolařství Jun',
            'from_address' => 'info@drevovyroba-jun.cz',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => MailSetting::ENCRYPTION_TLS,
            'smtp_username' => 'smtp-user',
            'smtp_password' => 'smtp-secret',
            'pop3_host' => 'pop3.example.com',
            'pop3_port' => 995,
            'pop3_encryption' => MailSetting::ENCRYPTION_SSL,
            'pop3_username' => 'pop3-user',
            'pop3_password' => 'pop3-secret',
        ], $attributes));
    }

    /**
     * The tester the page resolves, with the session of the handshake replaced, so no test opens a socket.
     *
     * @return SmtpConnectionTester the tester, with the transport of the last test and the handshake count recorded
     */
    private function scriptedTester(): SmtpConnectionTester
    {
        $tester = new class(app(MailSettingsMailer::class)) extends SmtpConnectionTester
        {
            public ?MailSetting $testedSettings = null;

            public ?EsmtpTransport $handshaken = null;

            public ?Throwable $failure = null;

            public int $handshakes = 0;

            protected function transport(MailSetting $settings): EsmtpTransport
            {
                $this->testedSettings = $settings;

                return parent::transport($settings);
            }

            protected function handshake(EsmtpTransport $transport): void
            {
                ++$this->handshakes;

                $this->handshaken = $transport;

                if ($this->failure instanceof Throwable) {
                    throw $this->failure;
                }
            }
        };

        $this->app->instance(SmtpConnectionTester::class, $tester);

        return $tester;
    }

    private function actingAsSuperAdmin(): User
    {
        $role = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin->fresh());

        return $admin;
    }

    /**
     * A user who may open the page, which is the permission the panel grants to the office.
     */
    private function officeUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('View:MailSettings', 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
