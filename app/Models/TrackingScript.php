<?php

namespace App\Models;

use App\Enums\Compliance\ScriptPosition;
use App\Enums\Compliance\TrackingCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A cookie or a tracking service of the site.
 *
 * Two kinds of record live in the same table, because both belong into the same overview the cookie
 * policy publishes:
 *
 * - a `Necessary` record documents a first-party technical cookie (session, form protection, the
 *   stored cookie choice) and carries no code, so it is never executed anywhere;
 * - a record of an optional category carries the snippet itself and is executed by the browser only
 *   after the visitor granted that category in the cookie dialog.
 *
 * The consent requirement of an optional category can not be switched off: `booted()` normalises the
 * flag on every save, so a forgotten toggle in the administration can never turn a tracking script
 * into one that runs without consent.
 */
class TrackingScript extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'position',
        'description',
        'provider_name',
        'provider_privacy_url',
        'code',
        'enabled',
        'requires_consent',
        'priority',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $script): void {
            if ($script->category instanceof TrackingCategory && $script->category->isOptional()) {
                $script->requires_consent = true;
            }
        });
    }

    /**
     * @param  Builder<TrackingScript>  $query
     * @return Builder<TrackingScript>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * The scripts the browser may load, in the order they are inserted: the necessary ones first,
     * because they describe the technical operation of the page.
     *
     * @return Collection<int, self>
     */
    public static function publishedScripts(): Collection
    {
        return self::query()->enabled()->orderBy('priority')->orderBy('id')->get();
    }

    /**
     * Whether the browser has to ask before running this record.
     *
     * A record of an optional category always requires consent, no matter what the flag says; the
     * flag only matters for a necessary record that an operator wants to keep visible but never run.
     */
    public function needsConsent(): bool
    {
        if ($this->category instanceof TrackingCategory && $this->category->isOptional()) {
            return true;
        }

        return (bool) $this->requires_consent;
    }

    /**
     * The record as the cookie dialog and the browser loader read it.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category?->value,
            'position' => ($this->position ?? ScriptPosition::BodyEnd)->value,
            'description' => $this->description,
            'providerName' => $this->provider_name,
            'providerPrivacyUrl' => $this->provider_privacy_url,
            'code' => $this->code,
            'requiresConsent' => $this->needsConsent(),
            'priority' => (int) $this->priority,
        ];
    }

    /**
     * Per-category counts of the optional scripts the site may load.
     *
     * The cookie dialog derives its configurable categories from this data, so a category without a
     * single enabled script is never offered and the visitor is not asked for consent to nothing.
     *
     * @return array<string, int>
     */
    public static function optionalCategoryCounts(): array
    {
        $counts = [];

        foreach (self::query()->enabled()->get() as $script) {
            $category = $script->category?->value;

            if ($category === null || ! $script->category->isOptional()) {
                continue;
            }

            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TrackingCategory::class,
            'position' => ScriptPosition::class,
            'enabled' => 'boolean',
            'requires_consent' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
