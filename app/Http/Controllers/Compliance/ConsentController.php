<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\Compliance\ConsentType;
use App\Http\Controllers\Controller;
use App\Services\Compliance\ConsentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stores the cookie choice of the cookie dialog.
 *
 * The endpoint accepts cookie consents only: a GDPR acknowledgement belongs to a submitted form and is
 * written by the server that accepted the form (App\Services\Compliance\ConsentRecorder), so nobody can
 * create evidence of an acknowledgement for somebody else.
 *
 * A browser without stored consent keeps working: the site only uses the technical cookies of the
 * session, and the dialog simply asks again on the next load.
 */
class ConsentController extends Controller
{
    public function __invoke(Request $request, ConsentRecorder $recorder): JsonResponse
    {
        $validated = $request->validate([
            'consent_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::in([ConsentType::Cookie->value])],
            'version' => ['nullable', 'string', 'max:64'],
            'preferences' => ['required', 'array'],
            // The necessary category is the technical operation of the site, so a submission that
            // claims it was refused is not a consent at all.
            'preferences.necessary' => ['accepted'],
            'preferences.analytics' => ['nullable', 'boolean'],
            'preferences.marketing' => ['nullable', 'boolean'],
            'preferences.preferences' => ['nullable', 'boolean'],
            'withdrawal' => ['nullable', 'boolean'],
        ]);

        $record = $recorder->recordCookieConsent(
            (string) $validated['consent_uuid'],
            (array) ($validated['preferences'] ?? []),
            $validated['version'] ?? null,
            $request,
            (bool) ($validated['withdrawal'] ?? false),
        );

        if ($record === null) {
            // The evidence table is not available yet; the browser keeps its local choice and will try
            // again after the next configuration load.
            return response()->json(['stored' => false], 202);
        }

        return response()->json([
            'stored' => true,
            'consent_uuid' => $record->consent_uuid,
            'version' => $record->version,
            'accepted_at' => $record->accepted_at?->toIso8601String(),
            'rejected_at' => $record->rejected_at?->toIso8601String(),
            'withdrawn_at' => $record->withdrawn_at?->toIso8601String(),
        ]);
    }
}
