<?php

namespace App\Http\Controllers;

use App\Models\Reference;
use Inertia\Inertia;
use Inertia\Response;

class ReferencesController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('References', [
            'referencesByCategory' => Reference::publishedCardsByCategory(),
        ]);
    }
}
