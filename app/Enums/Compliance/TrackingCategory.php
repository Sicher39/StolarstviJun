<?php

namespace App\Enums\Compliance;

/**
 * Category of a cookie or of a tracking script.
 *
 * `Necessary` is the technical operation of the site and is never asked for. Every other category is
 * optional: a script of such a category runs only after the visitor granted that category in the
 * cookie dialog.
 */
enum TrackingCategory: string
{
    case Necessary = 'necessary';
    case Analytics = 'analytics';
    case Marketing = 'marketing';
    case Preferences = 'preferences';

    public function label(): string
    {
        return match ($this) {
            self::Necessary => 'Nezbytné',
            self::Analytics => 'Analytické',
            self::Marketing => 'Marketingové',
            self::Preferences => 'Preferenční',
        };
    }

    /**
     * A category the visitor has to agree with before anything in it may run.
     */
    public function isOptional(): bool
    {
        return $this !== self::Necessary;
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

    /**
     * @return list<string>
     */
    public static function optionalValues(): array
    {
        $values = [];

        foreach (self::cases() as $case) {
            if ($case->isOptional()) {
                $values[] = $case->value;
            }
        }

        return $values;
    }
}
