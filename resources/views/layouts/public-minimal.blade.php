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

    @vite([$dir === 'rtl' ? 'resources/css/public-rtl.css' : 'resources/css/public-ltr.css', 'resources/js/public.js'])
    @stack('styles')
</head>
<body>
    {{--
        Deliberately stripped of the normal site navbar/footer for this
        single-purpose recruiting page — no "Home"/"Download App" exits for
        a driver mid-signup to wander off through, since the app isn't
        publicly live yet and those links only distract or dead-end. Just
        the logo for branding; no nav links, no buttons.
    --}}
    <header class="py-4 text-center">
        <img src="{{ asset('images/logo-x.png') }}" alt="{{ __('landing.common.app_name') }}" style="height: 40px;">
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="py-4 text-center small text-muted-ad">
        &copy; {{ now()->year }} {{ __('landing.common.app_name') }}. {{ __('landing.footer.copyright') }}
    </footer>

    <x-public.whatsapp-button />

    @stack('scripts')
</body>
</html>
