<?php

namespace App\Models;

use App\Enums\Compliance\LegalBasis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * One purpose for which the workshop processes personal data.
 *
 * The record is the single description of the scope of processing: the privacy policy publishes the
 * active purposes (ordered by `priority`), so a change of processing is made here and never retyped
 * into the legal text. `context` is the stable key of a seeded purpose, which lets
 * Database\Seeders\DataProcessingPurposeSeeder update its own records in place instead of creating
 * duplicates.
 */
class DataProcessingPurpose extends Model
{
    /**
     * Context of the purpose an inquiry is processed for. The inquiry form stores it in the consent
     * record, so the evidence points at the purpose instead of copying its name.
     */
    public const CONTEXT_INQUIRY = 'inquiry_handling';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'context',
        'description',
        'personal_data_categories',
        'legal_basis',
        'retention_period',
        'recipients',
        'third_country_transfer',
        'is_active',
        'priority',
    ];

    /**
     * @param  Builder<DataProcessingPurpose>  $query
     * @return Builder<DataProcessingPurpose>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The order the privacy policy publishes, and the order the administration lists.
     *
     * @param  Builder<DataProcessingPurpose>  $query
     * @return Builder<DataProcessingPurpose>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    /**
     * The purposes the privacy policy currently publishes.
     *
     * @return Collection<int, self>
     */
    public static function publishedPurposes(): Collection
    {
        return self::query()->active()->ordered()->get();
    }

    public function legalBasisLabel(): ?string
    {
        return $this->legal_basis?->label();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legal_basis' => LegalBasis::class,
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
