<?php

namespace App\Services\Ares;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads public company data from the ARES open API (ekonomicke-subjekty-v-be).
 *
 * The endpoint is a hardcoded constant on purpose: the caller can only pass an IČ, never a URL, host or path, so the
 * import can never be pointed to another server. The service returns only the whitelisted contact attributes that
 * ARES really returned; it never persists anything and never returns the raw upstream payload.
 */
class AresCompanyLookup
{
    /**
     * ARES REST endpoint; the validated IČ is appended as the last path segment.
     */
    private const ENDPOINT = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty';

    private const CONNECT_TIMEOUT_SECONDS = 2;

    private const TIMEOUT_SECONDS = 5;

    /**
     * One initial attempt plus two retries for transient failures.
     */
    private const MAX_ATTEMPTS = 3;

    private const RETRY_DELAY_MILLISECONDS = 200;

    /**
     * Rate limiting and the server errors ARES may recover from.
     */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    /**
     * Length of the `contacts` columns; a longer ARES value is skipped instead of being truncated.
     */
    private const MAX_VALUE_LENGTH = 255;

    /**
     * Weights of the first seven digits of a Czech IČ for the modulo 11 check digit.
     */
    private const CHECKSUM_WEIGHTS = [8, 7, 6, 5, 4, 3, 2];

    /**
     * Fetch the company data for a validated Czech IČ.
     *
     * @return array<string, string> Contact attributes in the `contacts` column names, limited to the values ARES
     *                               actually returned, so missing values can never overwrite manual data.
     *
     * @throws AresLookupException
     */
    public function forRegistrationNumber(string $registrationNumber): array
    {
        $registrationNumber = trim($registrationNumber);

        if (! $this->isValidRegistrationNumber($registrationNumber)) {
            throw AresLookupException::invalidRegistrationNumber();
        }

        $response = $this->request($registrationNumber);

        if ($response->status() === 404) {
            throw AresLookupException::notFound();
        }

        if ($response->failed()) {
            throw AresLookupException::unavailable();
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw AresLookupException::invalidResponse();
        }

        $returnedRegistrationNumber = is_string($payload['ico'] ?? null) ? trim($payload['ico']) : '';

        if ($returnedRegistrationNumber !== $registrationNumber) {
            throw AresLookupException::registrationNumberMismatch();
        }

        return $this->mapContactAttributes($payload, $registrationNumber);
    }

    /**
     * @throws AresLookupException
     */
    private function request(string $registrationNumber): Response
    {
        try {
            return Http::acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(
                    self::MAX_ATTEMPTS,
                    self::RETRY_DELAY_MILLISECONDS,
                    $this->isRetryableFailure(...),
                    throw: false,
                )
                ->get(self::ENDPOINT.'/'.$registrationNumber);
        } catch (ConnectionException|RequestException) {
            throw AresLookupException::unavailable();
        }
    }

    /**
     * Retry only transient failures: network errors, rate limiting and server errors. Any other 4xx is a definitive
     * answer of ARES and is not repeated. The exception is nullable because Laravel passes `null` for a response that
     * is neither successful nor failed.
     */
    private function isRetryableFailure(?Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            return in_array($exception->response->status(), self::RETRYABLE_STATUSES, true);
        }

        return false;
    }

    /**
     * Map the ARES subject document onto the editable contact attributes.
     *
     * Fields ARES does not guarantee (contact person, e-mail, phone, map links, data box, bank account, registry note)
     * are intentionally not mapped at all, so their manually maintained values stay untouched.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function mapContactAttributes(array $payload, string $registrationNumber): array
    {
        $attributes = ['registration_number' => $registrationNumber];

        $companyName = $this->stringOrNull($payload['obchodniJmeno'] ?? null);

        if ($companyName !== null) {
            $attributes['company_name'] = $companyName;
        }

        // `dic` is absent for subjects that are not registered for VAT; an empty value must not delete the manual one.
        $vatNumber = $this->stringOrNull($payload['dic'] ?? null);

        if ($vatNumber !== null) {
            $attributes['vat_number'] = $vatNumber;
        }

        $seat = $payload['sidlo'] ?? null;

        if (! is_array($seat)) {
            return $attributes;
        }

        $street = $this->mapStreet($seat);

        if ($street !== null) {
            $attributes['street'] = $street;
        }

        $city = $this->mapCity($seat);

        if ($city !== null) {
            $attributes['city'] = $city;
        }

        return $attributes;
    }

    /**
     * Compose "Ulice a číslo popisné" from the structured `sidlo` parts.
     *
     * Both the street name and the house number are required: a half address would replace the manual value with
     * something incomplete, which is worse than leaving it as it is.
     *
     * @param  array<string, mixed>  $seat
     */
    private function mapStreet(array $seat): ?string
    {
        $streetName = $this->stringOrNull($seat['nazevUlice'] ?? null);
        $houseNumber = $this->positiveIntegerOrNull($seat['cisloDomovni'] ?? null);

        if ($streetName === null || $houseNumber === null) {
            return null;
        }

        $street = $streetName.' '.$houseNumber;

        $orientationNumber = $this->positiveIntegerOrNull($seat['cisloOrientacni'] ?? null);

        if ($orientationNumber !== null) {
            $orientationLetter = $this->stringOrNull($seat['cisloOrientacniPismeno'] ?? null);

            $street .= '/'.$orientationNumber.($orientationLetter ?? '');
        }

        return $this->stringOrNull($street);
    }

    /**
     * Compose "PSČ a obec" from the postal code and the municipality of the `sidlo`.
     *
     * City districts and municipality parts are intentionally not used, the field only carries the postal code and
     * the municipality name.
     *
     * @param  array<string, mixed>  $seat
     */
    private function mapCity(array $seat): ?string
    {
        $postalCode = $this->formatPostalCode($seat['psc'] ?? null);
        $municipality = $this->stringOrNull($seat['nazevObce'] ?? null);

        if ($postalCode === null || $municipality === null) {
            return null;
        }

        return $this->stringOrNull($postalCode.' '.$municipality);
    }

    /**
     * ARES returns the postal code as an integer without the leading zero, so it is padded before the Czech
     * "123 45" formatting is applied.
     */
    private function formatPostalCode(mixed $postalCode): ?string
    {
        if (is_int($postalCode)) {
            $postalCode = (string) $postalCode;
        }

        if (! is_string($postalCode)) {
            return null;
        }

        $postalCode = trim($postalCode);

        if (preg_match('/^\d{1,5}$/', $postalCode) !== 1) {
            return null;
        }

        $postalCode = str_pad($postalCode, 5, '0', STR_PAD_LEFT);

        return substr($postalCode, 0, 3).' '.substr($postalCode, 3, 2);
    }

    /**
     * Normalise an ARES text value: trimmed, non-empty and within the length of the database column.
     */
    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || Str::length($value) > self::MAX_VALUE_LENGTH) {
            return null;
        }

        return $value;
    }

    private function positiveIntegerOrNull(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || $value <= 0) {
            return null;
        }

        return $value;
    }

    /**
     * Exactly eight digits with a valid Czech check digit (modulo 11).
     */
    private function isValidRegistrationNumber(string $registrationNumber): bool
    {
        if (preg_match('/^\d{8}$/', $registrationNumber) !== 1) {
            return false;
        }

        $weightedSum = 0;

        foreach (self::CHECKSUM_WEIGHTS as $index => $weight) {
            $weightedSum += $weight * (int) $registrationNumber[$index];
        }

        $remainder = $weightedSum % 11;

        $expectedCheckDigit = match ($remainder) {
            0 => 1,
            1 => 0,
            default => 11 - $remainder,
        };

        return $expectedCheckDigit === (int) $registrationNumber[7];
    }
}
