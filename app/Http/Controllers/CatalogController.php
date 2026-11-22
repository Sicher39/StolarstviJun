<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalog page of the public site. It shares the header of the other subpages; the catalog
 * content itself is filled in a later step, so no data is passed to the page yet.
 */
class CatalogController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Catalog');
    }
}
