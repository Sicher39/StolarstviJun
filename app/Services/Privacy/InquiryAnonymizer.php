<?php

namespace App\Services\Privacy;

use App\Models\Inquiry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Anonymizes configurator inquiries that are older than the retention period configured in the
 * administration. Only the inquiry domain exists in this project, so this is the only personal data
 * the command touches.
 *
 * What is removed: the name, e-mail, phone and message of the customer, plus the copy of the same
 * values inside `crm_payload`. What stays: the chosen door configuration and the indicative prices,
 * because they are the quote itself and carry no personal data. `anonymized_at` marks the record,
 * so a second run never touches it again and the administration can show its state.
 *
 * The class performs no scheduling on its own: running it is a manual, explicit decision.
 */
class InquiryAnonymizer
{
    /**
     * Values are obviously non-personal and use the reserved `.invalid` top level domain, so they
     * can not collide with a real mailbox.
     */
    public const REDACTED_NAME = 'Anonymizovaná poptávka';

    public const REDACTED_EMAIL = 'anonymizovano@example.invalid';

    /**
     * The moment before which inquiries are considered expired.
     */
    public function cutoff(int $months, ?CarbonInterface $now = null): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? CarbonImmutable::now())
            ->subMonths($months)
            ->startOfDay();
    }

    public function count(CarbonInterface $cutoff): int
    {
        return $this->eligible($cutoff)->count();
    }

    /**
     * Anonymize every eligible inquiry and return how many records were changed.
     */
    public function anonymize(CarbonInterface $cutoff): int
    {
        $anonymized = 0;

        $this->eligible($cutoff)->chunkById(100, function (Collection $inquiries) use (&$anonymized): void {
            foreach ($inquiries as $inquiry) {
                $this->anonymizeInquiry($inquiry);
                $anonymized++;
            }
        });

        return $anonymized;
    }

    /**
     * @return Builder<Inquiry>
     */
    private function eligible(CarbonInterface $cutoff): Builder
    {
        return Inquiry::query()
            ->whereNull('anonymized_at')
            ->where('created_at', '<=', $cutoff);
    }

    /**
     * `forceFill` is used on purpose: the personal data columns stay out of `$fillable`, so a
     * request or a form can never overwrite them this way.
     */
    private function anonymizeInquiry(Inquiry $inquiry): void
    {
        $inquiry->forceFill([
            'customer_name' => self::REDACTED_NAME,
            'customer_email' => self::REDACTED_EMAIL,
            'customer_phone' => null,
            'customer_message' => null,
            'crm_payload' => $this->redactPayload($inquiry->crm_payload),
            'anonymized_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function redactPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (array_key_exists('customer', $payload) && is_array($payload['customer'])) {
            $payload['customer'] = [
                'name' => self::REDACTED_NAME,
                'email' => self::REDACTED_EMAIL,
                'phone' => null,
                'message' => null,
            ];
        }

        return $payload;
    }
}
