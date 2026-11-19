<?php

namespace App\Services\Legal;

use App\Models\Contact;
use App\Models\PrivacySetting;

/**
 * Turns the HTML stored in a legal document into the HTML the public page renders.
 *
 * Two conventions are supported, both documented in `config/legal.php`:
 *
 * - `{{ company_name }}` is replaced by a value read from the contact record or from the privacy
 *   settings, so the published text follows the administration without being retyped. Substituted
 *   values are HTML-escaped, because the text itself is trusted HTML while a contact field is not.
 *   An unknown variable is left untouched, which makes the typo visible instead of silently
 *   emptying the sentence.
 *
 * - `[DOPLNIT: ...]` marks a legally material fact that the owner has to confirm. The marker stays
 *   in the rendered page on purpose: an incomplete legal text must be visible, not hidden.
 *
 * Besides the scalar variables the text may mark where a structured block belongs with a block token
 * (`{{ processing_purposes }}`, `{{ cookie_services }}`). A block is HTML built by
 * App\Services\Compliance\ComplianceContentBlocks, so the purposes and the cookie services are
 * published exactly where the author placed them instead of being appended below the document. A
 * block token the caller does not provide is left untouched, the same way an unknown variable is. */
class LegalContentRenderer
{
    public const PLACEHOLDER_PREFIX = '[DOPLNIT:';

    private const VARIABLE_PATTERN = '/\{\{\s*([a-z_]+)\s*\}\}/u';

    private const PLACEHOLDER_PATTERN = '/\[DOPLNIT:\s*([^\]]+)\]/u';

    /**
     * Values available to the text, keyed by the variable name used in `{{ ... }}`.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        $contact = Contact::current();

        return [
            'company_name' => (string) $contact->company_name,
            'street' => (string) $contact->street,
            'city' => (string) $contact->city,
            'full_address' => trim($contact->street.', '.$contact->city, ', '),
            'registration_number' => (string) $contact->registration_number,
            'vat_number' => (string) $contact->vat_number,
            'data_box' => (string) $contact->data_box,
            'bank_account' => (string) $contact->bank_account,
            'registry_note' => (string) $contact->registry_note,
            'contact_person' => (string) $contact->contact_person,
            'email' => (string) $contact->email,
            'phone' => (string) $contact->phone,
            'inquiry_retention_period' => $this->retentionPeriod(PrivacySetting::configuredRetentionMonths()),
        ];
    }

    /**
     * Replace every known block token with its prepared HTML and every known variable with the
     * current value. Unknown tokens are kept as they are, so a typo stays visible.
     *
     * @param  array<string, string>  $blocks  Prepared, trusted HTML keyed by the token name.
     */
    public function render(?string $content, array $blocks = []): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }

        $content = $this->insertBlocks($content, $blocks);
        $variables = $this->variables();

        return preg_replace_callback(
            self::VARIABLE_PATTERN,
            static function (array $match) use ($variables): string {
                $value = $variables[$match[1]] ?? null;

                return $value === null ? $match[0] : e($value);
            },
            $content,
        ) ?? '';
    }

    /**
     * Whether the text places the given block token. The public pages use it to decide whether they
     * still have to append the block below the document.
     */
    public static function placesBlock(?string $content, string $block): bool
    {
        if ($content === null || $block === '') {
            return false;
        }

        return preg_match(self::blockPattern($block), $content) === 1;
    }

    /**
     * @param  array<string, string>  $blocks
     */
    private function insertBlocks(string $content, array $blocks): string
    {
        foreach ($blocks as $block => $html) {
            $block = (string) $block;

            if ($block === '') {
                continue;
            }

            // A callback keeps the HTML literal: a replacement string would interpret `$` and `\`.
            $content = preg_replace_callback(
                self::blockPattern($block),
                static fn (): string => $html,
                $content,
            ) ?? $content;
        }

        return $content;
    }

    private static function blockPattern(string $block): string
    {
        return '/\{\{\s*'.preg_quote($block, '/').'\s*\}\}/u';
    }

    /**
     * Distinct placeholder descriptions of a text, in the order they appear. The administration
     * lists them so the editor sees what still has to be confirmed.
     *
     * @return list<string>
     */
    public function placeholders(?string $content): array
    {
        if ($content === null || trim($content) === '') {
            return [];
        }

        $matches = [];

        if (preg_match_all(self::PLACEHOLDER_PATTERN, $content, $found) === false) {
            return [];
        }

        foreach ($found[1] as $description) {
            $description = trim((string) $description);

            if ($description === '' || in_array($description, $matches, true)) {
                continue;
            }

            $matches[] = $description;
        }

        return $matches;
    }

    /**
     * The retention period as it appears in the sentence, or the placeholder when it is not set.
     */
    public function retentionPeriod(?int $months): string
    {
        if ($months === null || $months < 1) {
            return self::PLACEHOLDER_PREFIX.' doba uchovávání poptávek]';
        }

        return $months.' '.$this->monthsWord($months);
    }

    private function monthsWord(int $months): string
    {
        if ($months === 1) {
            return 'měsíc';
        }

        if ($months >= 2 && $months <= 4) {
            return 'měsíce';
        }

        return 'měsíců';
    }
}
