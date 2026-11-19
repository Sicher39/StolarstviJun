<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton with the privacy decisions that are not part of the legal text itself.
 *
 * Today it holds a single value: how long inquiries from the configurator are kept before they are
 * anonymized. The value is intentionally nullable: until the owner decides, the legal text shows a
 * `[DOPLNIT: ...]` placeholder and `gdpr:anonymize-inquiries` refuses to run, so no retention period
 * is invented by the code.
 */
class PrivacySetting extends Model
{
    /**
     * Upper bound accepted by the administration. It only guards against a typo, it is not a legal
     * statement.
     */
    public const MAX_RETENTION_MONTHS = 120;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inquiry_retention_months',
    ];

    /**
     * The editable singleton. The oldest row is taken, so a legacy database with more than one row
     * still resolves deterministically.
     */
    public static function current(): self
    {
        $settings = static::query()->oldest('id')->first();

        if ($settings instanceof self) {
            return $settings;
        }

        return static::query()->create(['inquiry_retention_months' => null]);
    }

    /**
     * Read-only accessor used by the legal text and the console command: it must never create a row
     * during a public page render, and an unset value stays unset.
     */
    public static function configuredRetentionMonths(): ?int
    {
        $months = static::query()->oldest('id')->value('inquiry_retention_months');

        if ($months === null) {
            return null;
        }

        $months = (int) $months;

        return $months >= 1 ? $months : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inquiry_retention_months' => 'integer',
        ];
    }
}
