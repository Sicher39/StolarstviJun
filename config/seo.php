<?php

use App\Models\SeoPage;

/*
|--------------------------------------------------------------------------
| SEO a AEO metadata veřejných stránek
|--------------------------------------------------------------------------
|
| Every public page keeps its metadata here. The values are mirrored into the `seo_pages`
| table by Database\Seeders\SeoPagesSeeder and edited in the administration afterwards.
| The configuration is the fallback used when a row is missing (or the table does not exist
| yet), so the first response never renders an incomplete <head>.
|
| Keys are route names, so the resolver can find the right record for the current request.
| The single `global` key holds the site wide defaults (sharing image, social profiles,
| locality, organization structured data) that every page inherits.
|
| Descriptions are written in Czech, because the whole frontend is Czech only.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Firemní identita
    |--------------------------------------------------------------------------
    */

    'site_name' => 'Stolařství Jun a synové',

    /*
    | A title that already contains the site name is never suffixed twice.
    */

    'title_suffix' => ' | Stolařství Jun a synové',

    'default_robots' => 'index,follow',

    'og_site_name' => 'Stolařství Jun a synové',

    'og_locale' => 'cs_CZ',

    'og_type' => 'website',

    'twitter_card' => 'summary_large_image',

    /*
    | Twitter/X does not get its own image field on purpose: the Open Graph image is reused,
    | so the same picture always represents the page. The global record can override this
    | default without touching any page.
    */

    'default_og_image_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Stránky
    |--------------------------------------------------------------------------
    |
    | `in_sitemap` decides whether the page is listed in /sitemap.xml. The two legal pages (the privacy
    | policy and the cookie policy) are left out and marked as `noindex`, because they are legal
    | documents rather than pages offered to search visitors; their text is published from the legal
    | documents module (`config/legal.php`).
    |
    | Only keys that really belong to a route are seeded (the seeder skips the rest), so a
    | record always describes a page that the application can actually render.
    |
    */

    'pages' => [

        SeoPage::GLOBAL_KEY => [
            'label' => 'Globální nastavení',
            'title' => 'Stolařství Jun a synové',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => false,
            // The brief fields are stored in the explicit columns of `seo_pages` and the resolver,
            // the structured data and the form all address them by name.
            'business_name' => 'Stolařství Jun a synové s.r.o.',
            'street_address' => 'Stará Osada 540',
            'address_locality' => 'Zastávka u Brna',
            'postal_code' => '664 84',
            'address_country' => 'CZ',
            'schema_type' => 'Carpenter',
            'available_languages' => ['cs'],
            'offers_online' => true,
            // Coordinates and profile URLs stay empty until the workshop fills them in. Nothing is
            // invented here, because an approximate place on the map misleads local search.
            'latitude' => null,
            'longitude' => null,
            'social_facebook_url' => null,
            'social_instagram_url' => null,
            'social_linkedin_url' => null,
            'social_youtube_url' => null,
            'aeo_summary' => 'Rodinná stolařská dílna v Zastávce u Brna vyrábí interiérové i vchodové dveře na míru, schody, kuchyně, vestavěné skříně a další prvky z masivního dřeva. Zakázku řeší osobně od návrhu po montáž.',
            'aeo_search_intent' => 'Informační – hledá stolařství v Brně a okolí a zjišťuje, co všechno dílna umí vyrobit ze dřeva.',
            'aeo_entities' => ['stolařství Brno', 'dveře na míru', 'zakázková výroba ze dřeva', 'Zastávka u Brna'],
            'locality' => 'Zastávka u Brna',
            'area_served' => 'Brno a okolí',
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'Carpenter',
                'name' => 'Stolařství Jun a synové s.r.o.',
                'email' => 'info@drevovyroba-jun.cz',
                'telephone' => '+420 777 123 123',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Stará Osada 540',
                    'addressLocality' => 'Zastávka u Brna',
                    'postalCode' => '664 84',
                    'addressCountry' => 'CZ',
                ],
                'areaServed' => 'Brno a okolí',
            ],
        ],

        'front.index' => [
            'label' => 'Úvod',
            'title' => 'Dveře a zakázková výroba ze dřeva',
            'description' => 'Rodinná stolařská dílna v Zastávce u Brna. Vyrábíme interiérové i vchodové dveře na míru, schody, kuchyně, vestavěné skříně a další prvky z masivního dřeva.',
            'keywords' => 'stolařství Brno, dveře na míru, zakázková výroba ze dřeva, interiérové dveře, vchodové dveře, schody na míru',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => true,
            'schema_type' => 'Organization',
            'aeo_summary' => 'Rodinná stolařská dílna v Zastávce u Brna vyrábí dveře na míru, schody, kuchyně i vestavěné skříně z masivního dřeva. Zakázku řeší osobně od návrhu po montáž.',
            'aeo_search_intent' => 'Informační – hledá stolařství v Brně a okolí a zjišťuje, co všechno dílna umí vyrobit ze dřeva.',
            'aeo_entities' => ['stolařství Brno', 'dveře na míru', 'zakázková výroba ze dřeva', 'Zastávka u Brna'],
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Stolařství Jun a synové s.r.o.',
                'email' => 'info@drevovyroba-jun.cz',
                'telephone' => '+420 777 123 123',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Stará Osada 540',
                    'addressLocality' => 'Zastávka u Brna',
                    'postalCode' => '664 84',
                    'addressCountry' => 'CZ',
                ],
                'areaServed' => 'Brno a okolí',
            ],
        ],

        'front.doors' => [
            'label' => 'Dveře',
            'title' => 'Interiérové a vchodové dveře na míru',
            'description' => 'Dveře na míru z masivního dřeva i dýhy. Interiérové dveře, bezpečné vchodové dveře, atypické rozměry, posuvné i klasické otevírání. Výroba i montáž.',
            'keywords' => 'dveře na míru, interiérové dveře, vchodové dveře, masivní dveře, bezfalcové dveře, dveře Brno',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => true,
            'schema_type' => 'CollectionPage',
            'aeo_summary' => 'Interiérové i vchodové dveře na míru z masivního dřeva a dýhy, falcové i bezfalcové, v atypických rozměrech a s možností posuvného otevírání. Součástí je výroba i montáž.',
            'aeo_search_intent' => 'Transakční – vybírá konkrétní typ dveří a ověřuje, zda je možné je vyrobit na míru.',
            'aeo_entities' => ['interiérové dveře', 'vchodové dveře', 'bezfalcové dveře', 'masivní dřevo', 'atypické rozměry'],
            'structured_data' => null,
        ],

        'front.configurator' => [
            'label' => 'Konfigurátor',
            'title' => 'Konfigurátor dveří – podoba a orientační cena',
            'description' => 'Navrhněte si dveře online: typ, rozměr, dekor, sklo i kování. Konfigurátor ihned zobrazí podobu dveří a orientační cenu včetně DPH.',
            'keywords' => 'konfigurátor dveří, cena dveří, dveře online, návrh dveří',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => true,
            'schema_type' => 'WebPage',
            'aeo_summary' => 'Konfigurátor dveří ukáže podobu dveří a orientační cenu s DPH podle zvoleného typu, rozměru, dekoru, skla a kování. Cena je orientační, závaznou nabídku připravuje dílna.',
            'aeo_search_intent' => 'Transakční – chce zjistit orientační cenu dveří na míru bez návštěvy showroomu.',
            'aeo_entities' => ['konfigurátor dveří', 'orientační cena s DPH', 'dveře na míru'],
            'structured_data' => null,
        ],

        'front.customProduction' => [
            'label' => 'Zakázková výroba',
            'title' => 'Zakázková výroba ze dřeva – kuchyně, nábytek, schody',
            'description' => 'Zakázková výroba ze dřeva: kuchyně na míru, vestavěné skříně, schody, obložení interiérů a atypický nábytek. Návrh, výroba i montáž.',
            'keywords' => 'zakázková výroba ze dřeva, kuchyně na míru, vestavěné skříně, schody na míru, nábytek na míru',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => true,
            'schema_type' => 'CollectionPage',
            'aeo_summary' => 'Zakázková výroba ze dřeva: kuchyně na míru, vestavěné skříně, jídelní a pracovní stoly, knihovny, schodiště a zábradlí, obložení stěn a atypický nábytek.',
            'aeo_search_intent' => 'Transakční – hledá výrobce nábytku, kuchyně nebo schodů na míru v Brně a okolí.',
            'aeo_entities' => ['kuchyně na míru', 'vestavěné skříně', 'schody na míru', 'atypický nábytek'],
            'structured_data' => null,
        ],

        'front.references' => [
            'label' => 'Reference',
            'title' => 'Reference – realizace ze dřeva',
            'description' => 'Prohlédněte si naše realizace: dveře, kuchyně, schody, vestavěný nábytek a další zakázky ze dřeva z Brna a okolí.',
            'keywords' => 'reference stolařství, realizace dveří, kuchyně na míru reference, schody reference',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => true,
            'schema_type' => 'CollectionPage',
            'aeo_summary' => 'Galerie dokončených realizací rozdělená na dveře, nábytek, kuchyně a ostatní zakázky. Fotografie pocházejí z vlastních zakázek dílny.',
            'aeo_search_intent' => 'Informační – hledá ukázky realizací a ověřuje kvalitu provedení.',
            'aeo_entities' => ['reference', 'realizace dveří', 'kuchyně na míru', 'schody'],
            'structured_data' => null,
        ],

        'front.contact' => [
            'label' => 'Kontakt',
            'title' => 'Kontakt – stolařství Zastávka u Brna',
            'description' => 'Stolařství Jun a synové s.r.o., Stará Osada 540, 664 84 Zastávka u Brna. Telefon +420 777 123 123, e-mail info@drevovyroba-jun.cz.',
            'keywords' => 'stolařství Zastávka u Brna, kontakt stolařství, stolařství Brno',
            'robots' => 'index,follow',
            'og_type' => 'website',
            'in_sitemap' => true,
            'schema_type' => 'ContactPage',
            'business_name' => 'Stolařství Jun a synové s.r.o.',
            'street_address' => 'Stará Osada 540',
            'address_locality' => 'Zastávka u Brna',
            'postal_code' => '664 84',
            'address_country' => 'CZ',
            'aeo_summary' => 'Kontakt na stolařství Jun a synové s.r.o.: Stará Osada 540, 664 84 Zastávka u Brna, telefon +420 777 123 123 a e-mail info@drevovyroba-jun.cz.',
            'aeo_search_intent' => 'Navigační – hledá adresu, telefon a e-mail dílny.',
            'aeo_entities' => ['Stará Osada 540', 'Zastávka u Brna', 'kontakt stolařství'],
            'locality' => 'Zastávka u Brna',
            'area_served' => 'Brno a okolí',
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'Carpenter',
                'name' => 'Stolařství Jun a synové s.r.o.',
                'email' => 'info@drevovyroba-jun.cz',
                'telephone' => '+420 777 123 123',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Stará Osada 540',
                    'addressLocality' => 'Zastávka u Brna',
                    'postalCode' => '664 84',
                    'addressCountry' => 'CZ',
                ],
            ],
        ],

        'front.gdpr' => [
            'label' => 'Ochrana osobních údajů',
            'title' => 'Ochrana osobních údajů',
            'description' => 'Informace o zpracování osobních údajů a o právech subjektů údajů ve společnosti Stolařství Jun a synové s.r.o.',
            'keywords' => null,
            'robots' => 'noindex,follow',
            'og_type' => 'website',
            'in_sitemap' => false,
            'schema_type' => 'WebPage',
            'structured_data' => null,
        ],

        'front.cookies' => [
            'label' => 'Zásady cookies',
            'title' => 'Zásady používání cookies',
            'description' => 'Přehled cookies a služeb, které web Stolařství Jun a synové s.r.o. používá, a způsob, jakým můžete své volitelné cookies povolit nebo odmítnout.',
            'keywords' => null,
            'robots' => 'noindex,follow',
            'og_type' => 'website',
            'in_sitemap' => false,
            'schema_type' => 'WebPage',
            'structured_data' => null,
        ],

    ],

];
