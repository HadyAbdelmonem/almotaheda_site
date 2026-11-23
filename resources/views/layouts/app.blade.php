<!DOCTYPE html>
<html lang="ar" dir="rtl" data-motion="{{ setting('motion_level', 'full') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    <title>{{ $title ?? setting('store_name', __('store.name')) }}</title>
    {{-- SEO (spec §9): per-page meta description with a store fallback,
         Open Graph / Twitter cards, and owner-configured verification
         + analytics tags (rendered only when the settings are set). --}}
    <meta name="description" content="{{ $metaDescription ?? (setting('store_name', __('store.name')).' — '.__('store.tagline')) }}">
    <meta property="og:title" content="{{ $title ?? setting('store_name', __('store.name')) }}">
    <meta property="og:description" content="{{ $metaDescription ?? (setting('store_name', __('store.name')).' — '.__('store.tagline')) }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:locale" content="ar_EG">
    @isset($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endisset
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? setting('store_name', __('store.name')) }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? (setting('store_name', __('store.name')).' — '.__('store.tagline')) }}">
    @php $scVerification = trim((string) setting('search_console_verification', '')); @endphp
    @if($scVerification !== '')
        <meta name="google-site-verification" content="{{ $scVerification }}">
    @endif
    @php $ga4Id = trim((string) setting('ga4_measurement_id', '')); @endphp
    @if($ga4Id !== '')
        <meta name="ga4-id" content="{{ $ga4Id }}">
    @endif
    @isset($jsonLd)
        @foreach((array) $jsonLd as $ld)
            <script type="application/ld+json">{!! \App\Support\Seo::encode($ld) !!}</script>
        @endforeach
    @endisset
    @if($ga4Id !== '' && isset($gaEvents) && $gaEvents !== [])
        {{-- Server-built GA4 events (allowlist payloads, spec §9A). --}}
        <script type="application/json" data-ga-events>{!! json_encode($gaEvents, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
    {{-- Cairo font: self-hosted subset is wired in the storefront phases;
         font-display: swap keeps text visible while it loads (spec §10.3). --}}
    <style>
        @font-face {
            font-family: 'Cairo';
            font-display: swap;
            src: local('Cairo');
        }
    </style>
    {{-- Motion level downgrade only (spec §11.3): the browser may lower the
         owner's level for reduced-motion / Save-Data / weak devices, never raise it. --}}
    <script>
        (function(d){var e=d.documentElement,m=e.dataset.motion||'full',w=window,n=navigator,c=n.connection||{};
        e.classList.add('js');
        if(w.matchMedia&&w.matchMedia('(prefers-reduced-motion: reduce)').matches){m='off'}
        else if(m==='full'&&(c.saveData||/2g$/.test(c.effectiveType||'')||(n.deviceMemory&&n.deviceMemory<=2)||(n.hardwareConcurrency&&n.hardwareConcurrency<=2))){m='lite'}
        e.dataset.motion=m})(document);
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas text-body font-sans antialiased">
    {{-- Loading splash (owner override of the spec preloader ban, 2026-11-23):
         overlay only, first in the body, hidden on window load by splash.js
         with CSS/JS fallbacks so content is never trapped behind it. --}}
    <x-splash />

    <header>
        <x-site-header />
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer>
        <x-site-footer />
    </footer>

    {{-- WhatsApp float (spec §8.3); persisted so it does not replay per navigation. --}}
    @persist('whatsapp-float')
        <x-whatsapp-float />
    @endpersist

    {{-- Spark particle root (spec §11.5 fx.js); persisted across wire:navigate. --}}
    @persist('fx-root')
        <div id="fx-root" aria-hidden="true" style="position:fixed;inset:0;pointer-events:none;z-index:60;overflow:hidden"></div>
    @endpersist

    <x-toast />

    @livewireScripts
</body>
</html>
