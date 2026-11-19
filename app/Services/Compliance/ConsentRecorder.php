<?php

namespace App\Services\Compliance;

use App\Enums\Compliance\ConsentType;
use App\Models\ConsentRecord;
use App\Models\DataProcessingPurpose;
use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Writes the evidence of a consent.
 *
 * Two different flows share the recorder, because both have to answer the same question later: what
 * exactly did the person agree with, in which version of the site, and were they really the one who
 * submitted the form?
 *
 * - the cookie dialog keeps one mutable row per browser uuid, so a change of the categories rewrites
 *   the same evidence instead of piling up rows;
 * - the inquiry form appends one immutable row per submission, because a request for a quote is a
 *   separate act that has to stay provable on its own.
 *
 * Neither flow ever stores a raw IP address or user agent: both are kept as a salted hash only, which
 * is enough to recognise a repeated submission.
 */
class ConsentRecorder
{
    /**
     * The optional cookie categories, in the order the dialog offers them.
     */
    public const COOKIE_CATEGORIES = ['analytics', 'marketing', 'preferences'];

    /**
     * Source of a consent record written by the cookie dialog.
     */
    public const SOURCE_COOKIE_DIALOG = 'cookie_dialog';

    /**
     * Source of a consent record written by the inquiry form of the configurator.
     */
    public const SOURCE_CONFIGURATOR_INQUIRY = 'configurator_inquiry';

    /**
     * The wording the visitor confirms. Stored inside the snapshot, so the record proves what was on
     * the form even after the page is edited.
     */
    public const ACKNOWLEDGEMENT_TEXT = 'Odesláním poptávky potvrzuji, že jsem se seznámil/a se zásadami ochrany osobních údajů a rozumím tomu, jak budou mé údaje zpracovány.';

    /**
     * Record (or update) the cookie choice of one browser.
     */
    public function recordCookieConsent(
        string $consentUuid,
        array $preferences,
        ?string $version,
        Request $request,
        bool $withdrawal = false,
    ): ?ConsentRecord {
        if (! Schema::hasTable('consent_records')) {
            return null;
        }

        $normalizedPreferences = $this->normalizeCookiePreferences($preferences);
        $now = now();

        $record = ConsentRecord::query()->firstOrNew(['consent_uuid' => $consentUuid]);

        $record->fill([
            'type' => ConsentType::Cookie,
            'version' => $version,
            'preferences' => $normalizedPreferences,
            'source' => self::SOURCE_COOKIE_DIALOG,
            'channel' => 'web',
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
        ]);

        // Only a granted optional category is a consent. Refusing everything is recorded as a refusal,
        // and an explicit withdrawal keeps the moment it happened.
        if ($withdrawal) {
            $record->accepted_at = null;
            $record->rejected_at = null;
            $record->withdrawn_at = $now;
        } elseif ($this->hasOptionalGrant($normalizedPreferences)) {
            $record->accepted_at = $now;
            $record->rejected_at = null;
            $record->withdrawn_at = null;
        } else {
            $record->accepted_at = null;
            $record->rejected_at = $now;
            $record->withdrawn_at = null;
        }

        $record->save();

        return $record;
    }

    /**
     * Record the GDPR acknowledgement of one submitted form.
     *
     * The record keeps the version of the privacy policy that applied, the purpose it points at, the
     * subject details of the form and a snapshot of what was submitted, so the workshop can prove the
     * state of the site at the moment of the submission. Only the acknowledgement itself is stored
     * here: no marketing consent is asked for and none is recorded.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function recordGdprAcknowledgement(
        string $source,
        array $snapshot,
        Request $request,
        ?string $subjectName = null,
        ?string $subjectEmail = null,
        ?string $subjectPhone = null,
    ): ?ConsentRecord {
        if (! Schema::hasTable('consent_records')) {
            return null;
        }

        $document = $this->currentPrivacyPolicy();
        $purpose = $this->inquiryPurpose();

        return ConsentRecord::query()->create([
            'consent_uuid' => (string) Str::uuid(),
            'type' => ConsentType::Gdpr,
            'version' => $document->version,
            'preferences' => [
                'acknowledgement' => self::ACKNOWLEDGEMENT_TEXT,
                'privacy_policy_url' => route('front.gdpr'),
                'privacy_policy_version' => $document->version,
                ...$snapshot,
            ],
            'source' => $source,
            'purpose' => $purpose?->name ?? 'Vyřízení poptávky a příprava nabídky',
            'subject_name' => $subjectName,
            'subject_email' => $subjectEmail,
            'subject_phone' => $subjectPhone,
            'channel' => 'web',
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'legal_document_id' => $document->getKey(),
            'data_processing_purpose_id' => $purpose?->getKey(),
            'accepted_at' => now(),
        ]);
    }

    /**
     * The four categories as plain booleans.
     *
     * The necessary category is always granted - it is the technical operation of the site - and a
     * missing category counts as refused, which keeps the record fail-closed.
     *
     * @param  array<string, mixed>  $preferences
     * @return array{necessary: bool, analytics: bool, marketing: bool, preferences: bool}
     */
    public function normalizeCookiePreferences(array $preferences): array
    {
        return [
            'necessary' => true,
            'analytics' => ($preferences['analytics'] ?? false) === true,
            'marketing' => ($preferences['marketing'] ?? false) === true,
            'preferences' => ($preferences['preferences'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    private function hasOptionalGrant(array $preferences): bool
    {
        foreach (self::COOKIE_CATEGORIES as $category) {
            if (($preferences[$category] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The privacy policy that applies right now: the published record, or the configured baseline
     * while the record is still a draft.
     */
    private function currentPrivacyPolicy(): LegalDocument
    {
        if (! Schema::hasTable('legal_documents')) {
            return LegalDocument::baseline(LegalDocument::TYPE_PRIVACY_POLICY);
        }

        return LegalDocument::publishedFor(LegalDocument::TYPE_PRIVACY_POLICY)
            ?? LegalDocument::baseline(LegalDocument::TYPE_PRIVACY_POLICY);
    }

    private function inquiryPurpose(): ?DataProcessingPurpose
    {
        if (! Schema::hasTable('data_processing_purposes')) {
            return null;
        }

        return DataProcessingPurpose::query()
            ->where('context', DataProcessingPurpose::CONTEXT_INQUIRY)
            ->first();
    }

    private function hashValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', $value.'|'.(string) config('app.key'));
    }
}
