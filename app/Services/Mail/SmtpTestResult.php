<?php

namespace App\Services\Mail;

/**
 * Outcome of the read-only SMTP connection test.
 *
 * The check opens a session with the stored or just typed settings - the TCP connection, the encryption and the SMTP
 * login - and hangs up without handing a message over, so the values describe the connection and never a sent mail.
 * The wording of every case is user facing and deliberately free of credentials, server names, server answers and raw
 * transport errors: the office learns what to fix, not what the server said.
 *
 * An answered session that accepted the login is `Authenticated`; a refused login is a case of its own, because the fix
 * is a different one. Everything that prevents the handshake - an unknown server, a refused or broken encryption, a
 * timeout - is reported as one of those cases, and anything else stays the plain, detail-free failure.
 */
enum SmtpTestResult: string
{
    case Authenticated = 'authenticated';
    case InvalidCredentials = 'invalid_credentials';
    case NotConfigured = 'not_configured';
    case TlsUnavailable = 'tls_unavailable';
    case TimedOut = 'timed_out';
    case Unreachable = 'unreachable';
    case UnexpectedAnswer = 'unexpected_answer';

    public function title(): string
    {
        return match ($this) {
            self::Authenticated => 'SMTP spojení funguje',
            self::InvalidCredentials => 'SMTP server odmítl přihlášení',
            self::NotConfigured => 'Chybí údaje pro SMTP spojení',
            self::TlsUnavailable => 'SMTP server nepovolil zabezpečené spojení',
            self::TimedOut => 'SMTP server neodpověděl včas',
            self::Unreachable => 'Na SMTP server se nepodařilo připojit',
            self::UnexpectedAnswer => 'SMTP spojení se nepodařilo ověřit',
        };
    }

    public function body(): string
    {
        return match ($this) {
            self::Authenticated => 'Server přijal spojení, zabezpečení i přihlášení. Testovací e-mail se neodesílá a nastavení se neukládá.',
            self::InvalidCredentials => 'Server odmítl uživatelské jméno nebo heslo. Zkontrolujte je - pozor na skryté mezery, které se do pole vkládají při kopírování - a zkuste to znovu.',
            self::NotConfigured => 'Doplňte server, uživatelské jméno a heslo. Test se spouští s hodnotami z formuláře, takže je můžete ověřit ještě před uložením.',
            self::TlsUnavailable => 'Server nepovolil zabezpečení podle zvolené volby Zabezpečení, nebo se nepodařilo ověřit jeho certifikát. Zkontrolujte volbu zabezpečení a port a zkuste to znovu.',
            self::TimedOut => 'Server přestal odpovídat. Zkontrolujte adresu serveru, port a připojení k internetu a zkuste to znovu.',
            self::Unreachable => 'Na server se nepodařilo připojit. Zkontrolujte adresu serveru a port.',
            self::UnexpectedAnswer => 'Server neodpověděl očekávaným způsobem, takže spojení potvrdit nelze. Zkontrolujte server, port a volbu zabezpečení. Podrobnosti se z bezpečnostních důvodů nezobrazují.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Authenticated => 'success',
            self::InvalidCredentials, self::Unreachable => 'danger',
            default => 'warning',
        };
    }
}
