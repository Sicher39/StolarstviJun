<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoPageResolver;
use Illuminate\Http\Response;

/**
 * XML sitemap of the public pages. Only pages that really exist, are meant to be indexed and are
 * listed as `sitemap => true` in config/seo.php are advertised to search engines.
 */
class SitemapController extends Controller
{
    public function __invoke(SeoPageResolver $seoPageResolver): Response
    {
        return response()
            ->view('partials.sitemap', [
                'entries' => $seoPageResolver->sitemapEntries(),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
