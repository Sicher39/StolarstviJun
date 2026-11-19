<?php

namespace App\Services\Mail;

use App\Models\MailSetting;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;

/**
 * Builds the mail configuration of the application from the mailbox stored in the database.
 *
 * Nothing is read from `.env`: the server, the port, the encryption, the sender and both credentials come from the
 * {@see MailSetting} row maintained in the admin panel, which is what lets the office change them without a deployment.
 *
 * The stored mailbox is written under a stable name, so the container and the facade cache it like any other mailer;
 * because the settings may be edited at any time, the cached transport is dropped before the mailer is built again.
 * The credentials are handed to the transport only and never appear in an exception or a log entry.
 *
 * The encryption follows the stored value exactly: `ssl` is an implicit TLS connection, `tls` is STARTTLS and is
 * required (a server that cannot start TLS fails instead of receiving the credentials in clear text), and `none` is the
 * plain connection of a local relay.
 */
class MailSettingsMailer
{
    /**
     * The mailer the stored settings are written to. It becomes the default mailer of the application.
     */
    public const NAME = 'mailbox';

    /**
     * How long a send waits for an answer of the server.
     *
     * A message must not hang on an unreachable server forever. The connection test of the settings screen uses its own,
     * much shorter timeout and shares the rest of the configuration.
     */
    public const SendTimeoutSeconds = 15;

    /**
     * Writes the stored mailbox into the mail configuration and makes it the default mailer.
     *
     * A missing, switched-off or incomplete mailbox is left alone on purpose: the application then keeps the mailer of
     * the environment (`log` locally, for example), so a fresh installation and a site that has not been given its
     * credentials yet keep working exactly as before.
     *
     * @return bool whether the stored settings are complete and were applied
     */
    public function register(?MailManager $manager = null): bool
    {
        $manager ??= Mail::getFacadeRoot();

        if (! $manager instanceof MailManager) {
            return false;
        }

        $settings = MailSetting::configured();

        if ($settings === null || ! $settings->canSendMail()) {
            return false;
        }

        config()->set('mail.mailers.'.self::NAME, $this->configuration($settings));
        config()->set('mail.default', self::NAME);
        config()->set('mail.from', $this->from($settings));

        // The mailer may have been built from the previous settings of the row, and the manager keeps the built
        // transport; dropping it is what makes a change in the panel effective without a restart.
        $manager->purge(self::NAME);

        return true;
    }

    /**
     * The mailer of the stored mailbox, for a caller that sends explicitly instead of through the default mailer.
     *
     * @throws MailNotConfiguredException when the mailbox is missing, switched off or incomplete
     */
    public function for(?MailSetting $settings = null): Mailer
    {
        $settings ??= MailSetting::configured();

        if ($settings === null || ! $settings->canSendMail()) {
            throw MailNotConfiguredException::notConfigured();
        }

        config()->set('mail.mailers.'.self::NAME, $this->configuration($settings));
        config()->set('mail.from', $this->from($settings));

        $root = Mail::getFacadeRoot();

        if ($root instanceof MailManager) {
            $root->purge(self::NAME);
        }

        return Mail::mailer(self::NAME);
    }

    /**
     * The transport configuration of the stored mailbox.
     *
     * The very same array feeds the default mailer of the application and the connection test of the settings screen, so
     * the two can never disagree about the encryption of the mailbox.
     *
     * The server, the user name and the password are trimmed again here, because a row may have been stored before the
     * model started trimming them and a trailing space makes the login fail while the screen shows the right value. Only
     * the edges of the secret are removed - the space inside a password is handed over untouched.
     *
     * @return array<string, mixed>
     */
    public function configuration(MailSetting $settings, int $timeoutSeconds = self::SendTimeoutSeconds): array
    {
        $encryption = $settings->smtp_encryption ?? MailSetting::ENCRYPTION_TLS;

        return [
            'transport' => 'smtp',
            'scheme' => $encryption === MailSetting::ENCRYPTION_SSL ? 'smtps' : 'smtp',
            'host' => (string) MailSetting::normalizedIdentity($settings->smtp_host),
            'port' => $settings->smtp_port ?? $this->defaultPort($encryption),
            'username' => (string) MailSetting::normalizedIdentity($settings->smtp_username),
            'password' => (string) MailSetting::normalizedSecret($settings->smtp_password),
            'timeout' => $timeoutSeconds,
            'local_domain' => $this->localDomain(),
            'auto_tls' => $encryption !== MailSetting::ENCRYPTION_NONE,
            'require_tls' => $encryption === MailSetting::ENCRYPTION_TLS,
            'verify_peer' => true,
        ];
    }

    /**
     * The sender every message leaves with, so it does not have to be repeated at each call site.
     *
     * @return array{address: string, name: ?string}
     */
    public function from(MailSetting $settings): array
    {
        return [
            'address' => (string) MailSetting::normalizedIdentity($settings->from_address),
            'name' => MailSetting::normalizedIdentity($settings->from_name),
        ];
    }

    private function defaultPort(string $encryption): int
    {
        return $encryption === MailSetting::ENCRYPTION_SSL
            ? MailSetting::SMTP_DEFAULT_SSL_PORT
            : MailSetting::SMTP_DEFAULT_PORT;
    }

    private function localDomain(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
