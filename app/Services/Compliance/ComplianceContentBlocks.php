<?php

namespace App\Services\Compliance;

use App\Models\DataProcessingPurpose;
use App\Models\TrackingScript;

/**
 * Builds the structured blocks a legal document can insert into its own text.
 *
 * The authored text of the privacy policy and of the cookie policy marks where the managed data
 * belongs with a block token (`{{ processing_purposes }}`, `{{ cookie_services }}`). The blocks
 * themselves are rendered here, from the administration records, so a purpose or a service is
 * described once and published wherever the document places the token.
 *
 * A block is trusted HTML (it is built from Blade templates with escaped values), which is why
 * LegalContentRenderer inserts it verbatim while it escapes every scalar variable.
 */
class ComplianceContentBlocks
{
    public const PROCESSING_PURPOSES = 'processing_purposes';

    public const COOKIE_SERVICES = 'cookie_services';

    /**
     * The active purposes, as the privacy policy publishes them.
     */
    public function processingPurposes(): string
    {
        $purposes = DataProcessingPurpose::publishedPurposes();

        if ($purposes->isEmpty()) {
            return '';
        }

        return view('partials.compliance.processing-purposes', ['purposes' => $purposes])->render();
    }

    /**
     * Every enabled cookie and service, as the cookie policy publishes them.
     *
     * The necessary records come first, because they are the technical operation of the site; the
     * optional ones follow in the order the browser would insert them.
     */
    public function cookieServices(): string
    {
        $scripts = TrackingScript::publishedScripts()
            ->sortBy(fn (TrackingScript $script): int => ($script->category?->isOptional() ? 1 : 0) * 1000 + (int) $script->priority)
            ->values();

        if ($scripts->isEmpty()) {
            return '';
        }

        return view('partials.compliance.cookie-services', ['scripts' => $scripts])->render();
    }

    /**
     * @return array<string, string>
     */
    public function forPrivacyPolicy(): array
    {
        return [
            self::PROCESSING_PURPOSES => $this->processingPurposes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function forCookiePolicy(): array
    {
        return [
            self::COOKIE_SERVICES => $this->cookieServices(),
        ];
    }
}
