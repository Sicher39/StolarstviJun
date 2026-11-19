<?php

namespace App\Http\Controllers;

use App\Models\Reference;
use Inertia\Inertia;
use Inertia\Response;

class DoorsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Doors', [
            'references' => Reference::publishedCardsForPlacement(Reference::PLACEMENT_DOORS),
        ]);
    }
}
