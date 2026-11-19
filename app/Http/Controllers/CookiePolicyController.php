<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Services\Compliance\ComplianceContentBlocks;
use App\Services\Legal\LegalContentRenderer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public page of the cookie policy at /zasady-cookies.
 *
 * The page publishes the cookie policy document edited in the administration, or the configured
 * baseline while the record is only a draft - exactly like the privacy page, so the route always
 * answers and never shows an empty legal page.
 *
 * The document itself lists the cookies and services the site really uses: the text marks where they
 * belong with the `{{ cookie_services }}` token and the block is inserted there (see
 * App\Services\Compliance\ComplianceContentBlocks). A text written before the token existed still gets
 * the overview appended below the document, so the list is published once and never twice.
 */
class CookiePolicyController extends Controller
{
    public function __invoke(LegalContentRenderer $renderer, ComplianceContentBlocks $blocks): Response
    {
        $document = LegalDocument::bodyFor(LegalDocument::TYPE_COOKIE_POLICY);
        $content = $renderer->render($document->content, $blocks->forCookiePolicy());

        if (! LegalContentRenderer::placesBlock($document->content, ComplianceContentBlocks::COOKIE_SERVICES)) {
            $content .= $blocks->cookieServices();
        }

        return Inertia::render('CookiePolicy', [
            'document' => [
                'title' => $document->title,
                'perex' => $document->perex,
                'content' => $content,
                'version' => $document->version,
                'effective_from' => $document->effective_from?->toDateString(),
                'updated_at' => $document->updated_at?->toDateString(),
            ],
            'privacy_policy_url' => route('front.gdpr'),
        ]);
    }
}
