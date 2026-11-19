<?php

namespace App\Services\Mail;

use App\Models\MailSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * Read-only proof of the sending side of the mailbox.
 *
 * The check opens a real SMTP session against the configured server - the connection, the encryption and the login -
 * and hangs up. A message is never composed and never handed to the transport, so a test can never send an e-mail, and
 * nothing is written into the row either.
 *
 * The transport is built from {@see MailSettingsMailer::configuration()}, the very array the application sends with, so
 * the test proves the connection the site would really use: `ssl` is an implicit TLS connection, `tls` is a required
 * STARTTLS (a server that cannot start TLS fails instead of receiving the credentials in clear text) and `none` is the
 * plain connection of a local relay. Only the timeout differs - a screen cannot wait as long as a message - and the
 * mailer cache is deliberately not touched, because the settings under test may never have been saved at all.
 *
 * Nothing the transport says ever leaves this class: the result is one safe category, and the log line carries that
 * category only, never the message of the transport error, which repeats the server, the user name and sometimes the
 * whole SMTP answer.
 *
 * The class is open on purpose: `transport()` is the only place the transport is built and `handshake()` the only place
 * it is opened, so a test can watch the exact transport the given settings produce - including the credentials - without
 * a live server.
 */
class SmtpConnectionTester
{
    /**
     * The check runs while the office waits for the notification, so it gives up long before a message would.
     */
    public const TimeoutSeconds = 5;

    public function __construct(private readonly MailSettingsMailer $mailers) {}

    public function test(MailSetting $settings): SmtpTestResult
    {
        if (! $this->isConfigured($settings)) {
            return SmtpTestResult::NotConfigured;
        }

        $transport = null;

        try {
            $transport = $this->transport($settings);

            $this->handshake($transport);
        } catch (Throwable $exception) {
            return $this->failed($exception);
        } finally {
            if ($transport instanceof EsmtpTransport) {
                $this->disconnect($transport);
            }
        }

        return SmtpTestResult::Authenticated;
    }

    /**
     * The transport of the settings, in the configuration of the mailer of the application and with the timeout of the
     * check.
     *
     * The transport lives in this object only: nothing is written into `mail.mailers` and no cached mailer is dropped,
     * so a check of unsaved settings can never change how a real message leaves.
     *
     * @throws LogicException when the SMTP factory hands over anything else than a SMTP transport
     */
    protected function transport(MailSetting $settings): EsmtpTransport
    {
        $configuration = $this->mailers->configuration($settings, self::TimeoutSeconds);

        $transport = (new EsmtpTransportFactory)->create(new Dsn(
            (string) $configuration['scheme'],
            (string) $configuration['host'],
            (string) ($configuration['username'] ?? '') ?: null,
            (string) ($configuration['password'] ?? '') ?: null,
            $configuration['port'] ?? null,
            // The keys of the connection itself are not options of the DSN; the timeout is applied to the stream.
            Arr::except($configuration, ['transport', 'scheme', 'host', 'port', 'username', 'password', 'timeout']),
        ));

        if (! $transport instanceof EsmtpTransport) {
            throw new LogicException('The SMTP transport factory did not build a SMTP transport.');
        }

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout((float) self::TimeoutSeconds);
        }

        return $transport;
    }

    /**
     * The whole handshake: the greeting, EHLO with STARTTLS when the encryption asks for it, and the login.
     *
     * This is where the credentials are used and where a broken encryption stops the session. `send()` is never called,
     * so no addressee is ever named to the server.
     */
    protected function handshake(EsmtpTransport $transport): void
    {
        $transport->start();
    }

    /**
     * The polite `QUIT` of a finished session. Saying goodbye can never change a result that is already known, so a
     * failing command is ignored - the socket is closed either way.
     */
    private function disconnect(EsmtpTransport $transport): void
    {
        try {
            $transport->stop();
        } catch (Throwable) {
            // The transport drops the stream in `stop()` even when the server no longer answers.
        }
    }

    /**
     * Whether a session could be opened at all. The switched-off state of the row is not checked on purpose: the office
     * tests the sending side before it starts to use it.
     */
    private function isConfigured(MailSetting $settings): bool
    {
        return MailSetting::normalizedIdentity($settings->smtp_host) !== null
            && $settings->hasSmtpCredentials();
    }

    private function failed(Throwable $exception): SmtpTestResult
    {
        $result = $this->classify($exception);

        // Only the category is recorded. The message of the transport error repeats the server, the user name and
        // sometimes the whole answer, and the exception itself would be serialised with it.
        Log::warning('Mailbox SMTP connection test did not succeed.', [
            'result' => $result->value,
        ]);

        return $result;
    }

    /**
     * The category of a failed handshake.
     *
     * The text of the error is read here and only here, to tell the cases apart; it is never stored, logged or shown,
     * because it carries the credentials of the mailbox. The code of the mailer exception is the reliable signal of a
     * refused login, the rest are the stable phrases of the SMTP client and of the socket layer.
     */
    private function classify(Throwable $exception): SmtpTestResult
    {
        $code = (int) $exception->getCode();
        $message = strtolower($exception->getMessage());

        // 535 is the refusal of every authenticator of the server, 504 the absence of a supported mechanism.
        if (in_array($code, [504, 535], true) || $this->containsAny($message, ['authenticate'])) {
            return SmtpTestResult::InvalidCredentials;
        }

        if ($this->containsAny($message, ['timed out', 'timeout'])) {
            return SmtpTestResult::TimedOut;
        }

        // The phrases of a refused, broken or unverifiable encryption, both of the STARTTLS command and of the
        // handshake of an implicit TLS connection.
        if ($this->containsAny($message, ['starttls', 'tls required', 'crypto', 'certificate', 'ssl operation', 'ssl routines', 'handshake'])) {
            return SmtpTestResult::TlsUnavailable;
        }

        if ($this->containsAny($message, ['could not be established', 'connection refused', 'unable to connect', 'closed unexpectedly', 'unable to read'])) {
            return SmtpTestResult::Unreachable;
        }

        return SmtpTestResult::UnexpectedAnswer;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
