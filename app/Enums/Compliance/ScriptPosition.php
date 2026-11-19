<?php

namespace App\Enums\Compliance;

/**
 * Where the browser of an allowed tracking script inserts its snippet.
 *
 * The value only matters for a script that carries its own code; an informational entry of a
 * necessary cookie has no code and is therefore never inserted anywhere.
 */
enum ScriptPosition: string
{
    case Head = 'head';
    case BodyStart = 'body_start';
    case BodyEnd = 'body_end';

    public function label(): string
    {
        return match ($this) {
            self::Head => 'Hlavička (head)',
            self::BodyStart => 'Začátek body',
            self::BodyEnd => 'Konec body',
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
