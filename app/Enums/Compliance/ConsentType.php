<?php

namespace App\Enums\Compliance;

/**
 * What a stored consent record is evidence of.
 *
 * `Cookie` comes from the cookie dialog, `Gdpr` from the acknowledgement a visitor has to confirm
 * before sending an inquiry. `Marketing` exists for a later, separate opt-in: the quote form
 * deliberately never asks for it.
 */
enum ConsentType: string
{
    case Cookie = 'cookie';
    case Gdpr = 'gdpr';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Cookie => 'Cookies',
            self::Gdpr => 'GDPR – seznámení se zásadami',
            self::Marketing => 'Marketing',
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
