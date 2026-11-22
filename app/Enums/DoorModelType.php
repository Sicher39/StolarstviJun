<?php

namespace App\Enums;

/**
 * The surface finish of a door model.
 *
 * The configurator presents the foiled models first and the veneered ones second, so the enum owns
 * both the label the administration and the visitor see and the rank that order is built from. No
 * caller has to repeat the order, which keeps the listing and the catalog in step.
 */
enum DoorModelType: string
{
    case Foiled = 'foiled';

    case Veneered = 'veneered';

    public function label(): string
    {
        return match ($this) {
            self::Foiled => 'Foliové',
            self::Veneered => 'Dýhované',
        };
    }

    /**
     * Rank of the type in the configurator: a smaller number is offered earlier.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Foiled => 0,
            self::Veneered => 1,
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
