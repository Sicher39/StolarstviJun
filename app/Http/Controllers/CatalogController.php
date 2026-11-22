<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogListing;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalog page of the public site.
 *
 * It is a plain listing of the published offer, not a configurator: the visitor can read the models
 * with their variants and the decors, the glass, the frames and the hardware the models carry. The
 * page itself is filled by the `Catalog` component, the payload comes from `CatalogListing`.
 */
class CatalogController extends Controller
{
    public function __invoke(CatalogListing $catalogListing): Response
    {
        return Inertia::render('Catalog', [
            'listing' => $catalogListing->build(),
        ]);
    }
}
