<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Singleton with the mailbox the application sends its e-mail through.
 *
 * The whole connection - the server, the port, the encryption, the sender and both credentials - lives in this row and
 * is maintained in the admin panel, so none of it has to be part of `.env` and the office can change it without a
 * deployment. The credentials are encrypted at rest and both passwords are hidden from array output, so a stored secret
 * can never reach a browser in a form state or an API answer.
 *
 * The row also keeps the receiving (POP3) server. Nothing in the application opens a POP3 session today - there is no
 * inbox import - so those values are stored for the office, which keeps all mailbox data in one place, and nothing in
 * the application reads them yet.
 *
 * The addresses, the servers, both user names and both passwords are stored without the whitespace around them, and a
 * value that is empty afterwards is stored as `null`. A pasted value usually carries an invisible space (a non-breaking
 * or a zero-width space) that looks identical in the form but makes the mail server refuse the login, which is why the
 * rule lives here and not in the screen only. Only the edges of a value are ever touched: a space inside a password is
 * a part of it and is kept.
 */
class MailSetting extends Model
{
    /**
     * STARTTLS on the usual 587: the connection starts in clear text and is upgraded before the credentials are sent.
     */
    public const ENCRYPTION_TLS = 'tls';

    /**
     * Implicit TLS on 465: the socket is encrypted from the first byte.
     */
    public const ENCRYPTION_SSL = 'ssl';

    /**
     * A plain connection, used only by a local relay.
     */
    public const ENCRYPTION_NONE = 'none';

    public const SMTP_DEFAULT_PORT = 587;

    public const SMTP_DEFAULT_SSL_PORT = 465;

    public const POP3_DEFAULT_PORT = 995;

    /**
     * The choices of the two encryption selects, keyed by the stored value.
     *
     * @var array<string, string>
     */
    public const ENCRYPTIONS = [
        self::ENCRYPTION_TLS => 'TLS (STARTTLS)',
        self::ENCRYPTION_SSL => 'SSL/TLS',
        self::ENCRYPTION_NONE => 'Bez zabezpečení',
    ];

    /**
     * The attributes the settings page edits. Both passwords are missing on purpose: they are write-only, so a stored
     * secret is never filled back into the form.
     *
     * @var list<string>
     */
    public const EDITABLE_ATTRIBUTES = [
        'enabled',
        'from_name',
        'from_address',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'pop3_host',
        'pop3_port',
        'pop3_encryption',
        'pop3_username',
    ];

    /**
     * The values that identify the account.
     *
     * @var list<string>
     */
    private const IDENTITY_ATTRIBUTES = [
        'from_address',
        'from_name',
        'smtp_host',
        'smtp_username',
        'pop3_host',
        'pop3_username',
    ];

    /**
     * The two credentials. They follow the same edge rule as the user names.
     *
     * @var list<string>
     */
    private const SECRET_ATTRIBUTES = [
        'smtp_password',
        'pop3_password',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'enabled',
        'from_name',
        'from_address',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'pop3_host',
        'pop3_port',
        'pop3_encryption',
        'pop3_username',
        'pop3_password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'smtp_password',
        'pop3_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'smtp_port' => 'integer',
            'pop3_port' => 'integer',
            'smtp_username' => 'encrypted',
            'smtp_password' => 'encrypted',
            'pop3_username' => 'encrypted',
            'pop3_password' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $setting): void {
            $setting->normalizeStoredValues();
        });
    }

    /**
     * The one rule both layers share: the invisible characters around an address, a server, a user name or a secret are
     * removed and a value that is empty afterwards is stored as `null`, so `filled()` only ever sees a real value.
     *
     * `Str::trim()` is used instead of the plain `trim()`, because the usual copy-paste artefacts are a non-breaking
     * space or a zero-width space, which `trim()` keeps and the mail server then refuses.
     */
    public static function normalizedIdentity(?string $value): ?string
    {
        return self::withoutEdgeWhitespace($value);
    }

    /**
     * The same edge rule for a credential, used both when the row is saved and when a connection is built from it, so a
     * secret stored with a pasted space still logs in without a write into the database.
     */
    public static function normalizedSecret(?string $value): ?string
    {
        return self::withoutEdgeWhitespace($value);
    }

    /**
     * The editable singleton, created only by the settings page. It is never created here: a public page or a console
     * command must not write a row just to read a setting.
     */
    public static function current(): self
    {
        $settings = static::query()->oldest('id')->first();

        if ($settings instanceof self) {
            return $settings;
        }

        return static::query()->create(['enabled' => false]);
    }

    /**
     * The stored mailbox, or `null` while the table is still empty. Read-only, so the mail configuration can look the
     * settings up without writing anything.
     */
    public static function configured(): ?self
    {
        $settings = static::query()->oldest('id')->first();

        return $settings instanceof self ? $settings : null;
    }

    public function hasSmtpCredentials(): bool
    {
        // A secret of spaces only is not a credential, so it never looks like a configured mailbox.
        return filled($this->smtp_username) && self::normalizedSecret($this->smtp_password) !== null;
    }

    public function hasPop3Credentials(): bool
    {
        return filled($this->pop3_username) && self::normalizedSecret($this->pop3_password) !== null;
    }

    /**
     * Whether the application could send mail at all: the mailbox has to be switched on and the sending side has to be
     * complete. It only describes the stored settings, the sending itself is done by the mailer.
     *
     * The server, the sender and the user name are compared with their trimmed form, so a value of invisible
     * characters only counts as missing instead of looking configured and then failing in the header of the message.
     */
    public function canSendMail(): bool
    {
        return $this->enabled
            && self::normalizedIdentity($this->from_address) !== null
            && self::normalizedIdentity($this->smtp_host) !== null
            && $this->hasSmtpCredentials();
    }

    /**
     * Whether the row holds everything a POP3 session would need. It only describes the stored settings - the
     * application does not open such a session yet.
     */
    public function canReceiveMail(): bool
    {
        return $this->enabled
            && self::normalizedIdentity($this->pop3_host) !== null
            && $this->hasPop3Credentials();
    }

    /**
     * Every stored value is compared with its trimmed form, so a row stored before this rule existed is healed by its
     * next save - an edited mailbox, but just as well a save that changed only the sender.
     */
    private function normalizeStoredValues(): void
    {
        foreach (self::IDENTITY_ATTRIBUTES as $attribute) {
            $this->normalizeAttribute($attribute, self::normalizedIdentity(...));
        }

        foreach (self::SECRET_ATTRIBUTES as $attribute) {
            $this->normalizeAttribute($attribute, self::normalizedSecret(...));
        }
    }

    /**
     * A value that is empty after the trim is written as `null`, so `filled()` reads a blank secret as missing instead
     * of configured. A value that cannot be read as a string is left alone: it was not set at all.
     *
     * @param  callable(string): ?string  $normalizer
     */
    private function normalizeAttribute(string $attribute, callable $normalizer): void
    {
        $current = $this->getAttribute($attribute);

        if (! is_string($current)) {
            return;
        }

        $normalized = $normalizer($current);

        if ($normalized !== $current) {
            $this->setAttribute($attribute, $normalized);
        }
    }

    /**
     * Only the whitespace around the value is removed, never the whitespace inside it.
     */
    private static function withoutEdgeWhitespace(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) Str::trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
