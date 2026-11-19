<?php

namespace App\Services\Inquiry;

/**
 * The four internal statuses an inquiry moves through: new, in progress, approved, rejected.
 *
 * The stored keys are stable, because the configurator and the database default write them, and the
 * Czech labels are the only thing the administration shows. The status of an inquiry therefore can
 * never become free text again - the select in the edit form offers exactly these four values and
 * the list tabs filter by the very same keys.
 *
 * `Inquiry::$status` stays a plain string column on purpose: a row written before this vocabulary
 * existed (or changed outside the administration) still loads, and the table falls back to the raw
 * stored value instead of failing, so no legacy row has to be migrated to be readable.
 */
enum InquiryStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * The Czech label of the status, used by the select, the table badge and the list tabs.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'Nová',
            self::InProgress => 'Rozpracovaná',
            self::Approved => 'Schválená',
            self::Rejected => 'Odmítnutá',
        };
    }

    /**
     * The Filament badge colour that makes the state readable at a glance.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * Option list for the Filament select: `[stored key => Czech label]`, in the order the statuses
     * are usually worked through.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
