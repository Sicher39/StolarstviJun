<?php

namespace App\Enums\Compliance;

/**
 * Legal basis of one processing purpose, worded after article 6(1) of the GDPR.
 *
 * The value is stored in `data_processing_purposes.legal_basis`, so a purpose always declares why the
 * workshop is allowed to process the data instead of leaving the question to the free text.
 */
enum LegalBasis: string
{
    case Contract = 'contract';
    case LegalObligation = 'legal_obligation';
    case LegitimateInterest = 'legitimate_interest';
    case Consent = 'consent';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Plnění smlouvy nebo jednání o ní (čl. 6 odst. 1 písm. b)',
            self::LegalObligation => 'Právní povinnost (čl. 6 odst. 1 písm. c)',
            self::LegitimateInterest => 'Oprávněný zájem správce (čl. 6 odst. 1 písm. f)',
            self::Consent => 'Souhlas subjektu údajů (čl. 6 odst. 1 písm. a)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
