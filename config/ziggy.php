<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ziggy Route Groups
    |--------------------------------------------------------------------------
    |
    | The frontend bundle only needs the named routes of the Inertia pages, so
    | the export is scoped to the single `frontend` group below. The layout
    | emits it with @routes('frontend'), which keeps the package defaults for
    | URL, output classes and middleware untouched.
    |
    | Only `front.*` names belong here: the SEO routes (seo.sitemap and
    | seo.robots) are server rendered plain text responses, and the Filament
    | panel is never loaded by resources/js/front/app.ts.
    |
    | Add every new frontend route to this list, otherwise route() cannot
    | resolve it in the browser and throws "route is not in the route list".
    |
    */

    'groups' => [

        'frontend' => [
            'front.index',
            'front.doors',
            'front.configurator',
            'front.configurator.inquiry.store',
            'front.customProduction',
            'front.references',
            'front.contact',
            'front.gdpr',
            'front.cookies',
        ],

    ],

];
