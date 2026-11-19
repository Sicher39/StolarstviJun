<?php

namespace App\Http\Controllers;

use App\Models\Reference;
use Inertia\Inertia;
use Inertia\Response;

class CustomProductionController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('CustomProduction', [
            'references' => Reference::publishedCardsForPlacement(Reference::PLACEMENT_CUSTOM_PRODUCTION),
        ]);
    }
}
