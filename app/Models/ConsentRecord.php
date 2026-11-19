<?php

namespace App\Models;

use App\Enums\Compliance\ConsentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidence of one consent: what the visitor agreed to, when, in which version of the site and with
 * which details they identified themselves.
 *
 * Two flows write a record:
 *
 * - the cookie dialog (App\Http\Controllers\Compliance\ConsentController) keeps one row per browser
 *   uuid and rewrites the chosen categories, so a change of mind updates the same evidence;
 * - the inquiry form (App\Services\Compliance\ConsentRecorder) appends an immutable acknowledgement
 *   of the privacy policy that applied at the moment of the submission.
 *
 * `preferences` holds the JSON snapshot: the four cookie categories for a cookie consent, the
 * acknowledged wording plus the submitted configuration for a GDPR acknowledgement.
 */
class ConsentRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'consent_uuid',
        'type',
        'version',
        'preferences',
        'source',
        'purpose',
        'subject_name',
        'subject_email',
        'subject_phone',
        'channel',
        'ip_hash',
        'user_agent_hash',
        'legal_document_id',
        'data_processing_purpose_id',
        'accepted_at',
        'rejected_at',
        'withdrawn_at',
    ];

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    /**
     * @return BelongsTo<DataProcessingPurpose, $this>
     */
    public function dataProcessingPurpose(): BelongsTo
    {
        return $this->belongsTo(DataProcessingPurpose::class);
    }

    /**
     * The state of the consent in the words the administration shows.
     */
    public function statusLabel(): string
    {
        if ($this->withdrawn_at !== null) {
            return 'Odvoláno';
        }

        if ($this->accepted_at !== null) {
            return 'Přijato';
        }

        if ($this->rejected_at !== null) {
            return 'Odmítnuto';
        }

        return 'Bez záznamu';
    }

    /**
     * Whether the record proves a granted consent.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null && $this->withdrawn_at === null;
    }

    /**
     * The optional cookie categories the visitor granted.
     *
     * @return list<string>
     */
    public function grantedCookieCategories(): array
    {
        $preferences = (array) ($this->preferences ?? []);
        $granted = [];

        foreach (['analytics', 'marketing', 'preferences'] as $category) {
            if (($preferences[$category] ?? false) === true) {
                $granted[] = $category;
            }
        }

        return $granted;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'preferences' => 'array',
            'legal_document_id' => 'integer',
            'data_processing_purpose_id' => 'integer',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }
}
