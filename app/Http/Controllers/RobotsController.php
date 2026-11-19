<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoPageResolver;
use Illuminate\Http\Response;

/**
 * robots.txt with the sitemap location built from config('app.url').
 *
 * The file is answered by this route only: the former static public/robots.txt was removed, because a
 * web server serves an existing static file before Laravel is reached, which meant the sitemap line
 * could silently disappear in production.
 */
class RobotsController extends Controller
{
    public function __invoke(SeoPageResolver $seoPageResolver): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /up',
            '',
            'Sitemap: '.$seoPageResolver->sitemapUrl(),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
