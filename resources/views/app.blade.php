<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- styles -->
        <link rel="stylesheet" href="https://use.typekit.net/vxb0foc.css">

        <!-- Favicon and touch icons -->
        <link rel="shortcut icon" href="{{ asset('img/favicon/favicon.svg')}}">
        <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('img/favicon/apple-touch-icon-144.svg')}}">
        <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('img/favicon/apple-touch-icon-114.svg')}}">
        <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('img/favicon/apple-touch-icon-72.svg')}}">
        <link rel="apple-touch-icon" href="{{ asset('img/favicon/apple-touch-icon-57.svg')}}">

        <!-- The cookie dialog sends JSON requests to /api/compliance/consent, so the CSRF token has to be
             readable by the frontend bundle. -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Only the routes whitelisted in the `frontend` group of config/ziggy.php are exported. --}}
        @routes('frontend')
        @vite(['resources/js/front/app.ts'])
        @inertiaHead

        <!-- Title, meta, canonical, Open Graph and structured data of the current page. Rendered on
             the server, so crawlers and answer engines see them in the first response. -->
        @include('partials.seo')
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
