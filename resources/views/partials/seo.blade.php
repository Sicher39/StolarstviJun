@php
    // The payload is shared by the Inertia middleware, so this partial and the Vue SeoHead
    // component always render exactly the same values. The `inertia` attribute marks a tag as
    // managed by Inertia's <Head> component, so the client never leaves a duplicate behind.
    $seo = (array) data_get($page ?? [], 'props.seo', []);
@endphp

@if ($seo !== [])
    <title inertia>{{ $seo['title'] ?? '' }}</title>

    @if (filled($seo['description'] ?? null))
        <meta inertia head-key="description" name="description" content="{{ $seo['description'] }}">
    @endif
    @if (filled($seo['keywords'] ?? null))
        <meta inertia head-key="keywords" name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    <meta inertia head-key="robots" name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
    <link inertia head-key="canonical" rel="canonical" href="{{ $seo['canonical'] }}">

    <meta inertia head-key="og:title" property="og:title" content="{{ $seo['og_title'] }}">
    @if (filled($seo['og_description'] ?? null))
        <meta inertia head-key="og:description" property="og:description" content="{{ $seo['og_description'] }}">
    @endif
    <meta inertia head-key="og:type" property="og:type" content="{{ $seo['og_type'] }}">
    <meta inertia head-key="og:url" property="og:url" content="{{ $seo['og_url'] }}">
    @if (filled($seo['og_image'] ?? null))
        <meta inertia head-key="og:image" property="og:image" content="{{ $seo['og_image'] }}">
    @endif
    @if (filled($seo['og_site_name'] ?? null))
        <meta inertia head-key="og:site_name" property="og:site_name" content="{{ $seo['og_site_name'] }}">
    @endif
    @if (filled($seo['og_locale'] ?? null))
        <meta inertia head-key="og:locale" property="og:locale" content="{{ $seo['og_locale'] }}">
    @endif

    @if (filled($seo['twitter_card'] ?? null))
        <meta inertia head-key="twitter:card" name="twitter:card" content="{{ $seo['twitter_card'] }}">
    @endif
    {{-- The Twitter/X text of the brief falls back to the Open Graph text, so both stay in sync. --}}
    <meta inertia head-key="twitter:title" name="twitter:title" content="{{ $seo['twitter_title'] ?? $seo['og_title'] }}">
    @if (filled($seo['twitter_description'] ?? $seo['og_description'] ?? null))
        <meta inertia head-key="twitter:description" name="twitter:description" content="{{ $seo['twitter_description'] ?? $seo['og_description'] }}">
    @endif
    @if (filled($seo['twitter_image'] ?? $seo['og_image'] ?? null))
        <meta inertia head-key="twitter:image" name="twitter:image" content="{{ $seo['twitter_image'] ?? $seo['og_image'] }}">
    @endif

    @if (filled($seo['json_ld'] ?? null))
        {{-- The JSON is encoded with JSON_HEX_TAG, so a value can never close the script element. --}}
        <script inertia head-key="structured-data" type="application/ld+json">{!! $seo['json_ld'] !!}</script>
    @endif
@endif
