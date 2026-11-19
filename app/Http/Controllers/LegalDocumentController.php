<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Services\Compliance\ComplianceContentBlocks;
use App\Services\Legal\LegalContentRenderer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public page of the privacy policy. The route and the Inertia component keep their original name,
 * only the content moved from a hardcoded component to the legal document edited in the
 * administration.
 *
 * The controller renders the published record, or the configured baseline when the document has not
 * been published yet, so the page always exists.
 *
 * The overview of the processing purposes is not retyped into the text: the document marks where it
 * belongs with the `{{ processing_purposes }}` token and the block is inserted there (see
 * App\Services\Compliance\ComplianceContentBlocks). A text written before the token existed still gets
 * the overview appended below the document, so the scope of processing is always published.
 */
class LegalDocumentController extends Controller
{
    public function __invoke(LegalContentRenderer $renderer, ComplianceContentBlocks $blocks): Response
    {
        $document = LegalDocument::bodyFor(LegalDocument::TYPE_PRIVACY_POLICY);
        $content = $renderer->render($document->content, $blocks->forPrivacyPolicy());

        if (! LegalContentRenderer::placesBlock($document->content, ComplianceContentBlocks::PROCESSING_PURPOSES)) {
            $content .= $blocks->processingPurposes();
        }

        return Inertia::render('Gdpr', [
            'document' => [
                'title' => $document->title,
                'perex' => $document->perex,
                'content' => $content,
                'version' => $document->version,
                'effective_from' => $document->effective_from?->toDateString(),
                'updated_at' => $document->updated_at?->toDateString(),
            ],
            'cookie_policy_url' => route('front.cookies'),
        ]);
    }
}
