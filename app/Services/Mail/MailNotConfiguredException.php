<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Thrown when mail is sent while the mailbox has not been filled in yet.
 *
 * The message names the screen where the settings are maintained and never repeats a credential, a server or the
 * answer of a transport: the exception may travel to a log or to a queue entry.
 */
class MailNotConfiguredException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(
            'Odesílání e-mailů není nastavené. Doplňte adresu odesílatele a přihlašovací údaje k SMTP v Nastavení → E-mail.',
        );
    }
}
