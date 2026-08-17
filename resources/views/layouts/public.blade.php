<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $currentLocale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffae00">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">

    <title>@yield('meta_title', __('landing.meta.title'))</title>
    <meta name="description" content="@yield('meta_description', __('landing.meta.description'))">

    <link rel="canonical" href="{{ url()->current() }}">
    @foreach (config('localization.locales') as $code => $meta)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ alternate_locale_url($code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ alternate_locale_url(config('localization.default')) }}">

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ __('landing.common.app_name') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    <meta property="og:title" content="@yield('meta_title', __('landing.meta.title'))">
    <meta property="og:description" content="@yield('meta_description', __('landing.meta.description'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/logo-main.jpg') }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('meta_title', __('landing.meta.title'))">
    <meta name="twitter:description" content="@yield('meta_description', __('landing.meta.description'))">
    <meta name="twitter:image" content="{{ asset('images/logo-main.jpg') }}">

    {{-- Schema.org --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => __('landing.common.app_name'),
            'description' => __('landing.meta.description'),
            'url' => url('/'),
            'telephone' => config('marketing.contact_phone'),
            'email' => config('marketing.contact_email'),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Beirut',
                'addressCountry' => 'LB',
            ],
            'areaServed' => 'LB',
            'sameAs' => array_values(array_filter(config('marketing.social'))),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    @vite([$dir === 'rtl' ? 'resources/css/public-rtl.css' : 'resources/css/public-ltr.css', 'resources/js/public.js'])
    @stack('styles')

    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-G06MF7R95T"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-G06MF7R95T');
</script>
</head>
<body>
    <x-public.navbar />

    <main>
        @yield('content')
    </main>

    <x-public.footer />
    <x-public.whatsapp-button />

    @stack('scripts')
</body>
</html>
