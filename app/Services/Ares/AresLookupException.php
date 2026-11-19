<?php

namespace App\Services\Ares;

use RuntimeException;

/**
 * Failure of a single ARES lookup.
 *
 * The message is always a short Czech sentence that is safe to show to the administrator: it never contains the
 * upstream response body, the upstream error text or any part of a stack trace. The reason is a stable category
 * that can be logged without exposing anything else.
 */
class AresLookupException extends RuntimeException
{
    public const REASON_INVALID_REGISTRATION_NUMBER = 'invalid_registration_number';

    public const REASON_NOT_FOUND = 'not_found';

    public const REASON_UNAVAILABLE = 'unavailable';

    public const REASON_INVALID_RESPONSE = 'invalid_response';

    public const REASON_REGISTRATION_NUMBER_MISMATCH = 'registration_number_mismatch';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /**
     * The value is not one of the eight digits of a Czech IČ, or its check digit does not match.
     */
    public static function invalidRegistrationNumber(): self
    {
        return new self(
            self::REASON_INVALID_REGISTRATION_NUMBER,
            'IČ musí být osm číslic s platným kontrolním součtem.',
        );
    }

    /**
     * ARES knows the IČ format but has no subject registered under it.
     */
    public static function notFound(): self
    {
        return new self(
            self::REASON_NOT_FOUND,
            'Subjekt s tímto IČ nebyl v ARES nalezen.',
        );
    }

    /**
     * Network failure, timeout, rate limiting or a server side error on the ARES side.
     */
    public static function unavailable(): self
    {
        return new self(
            self::REASON_UNAVAILABLE,
            'ARES je momentálně nedostupné. Zkuste to prosím za chvíli.',
        );
    }

    /**
     * ARES answered, but not with the subject document this lookup expects.
     */
    public static function invalidResponse(): self
    {
        return new self(
            self::REASON_INVALID_RESPONSE,
            'ARES vrátilo neočekávanou odpověď, údaje nebyly načteny.',
        );
    }

    /**
     * The returned document belongs to a different IČ, so it must never be used.
     */
    public static function registrationNumberMismatch(): self
    {
        return new self(
            self::REASON_REGISTRATION_NUMBER_MISMATCH,
            'ARES vrátilo údaje jiného subjektu, údaje nebyly načteny.',
        );
    }
}
