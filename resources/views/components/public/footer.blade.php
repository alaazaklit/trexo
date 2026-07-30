@php
    $home = localized_route('landing.home');
@endphp
<footer class="ad-footer pt-5 pb-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <h5 class="text-white fw-bold mb-3">{{ __('landing.common.app_name') }}</h5>
                <p class="mb-4">{{ __('landing.footer.about') }}</p>
                <div class="d-flex gap-2">
                    @foreach (config('marketing.social') as $network => $url)
                        @if ($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="social-icon" aria-label="{{ ucfirst($network) }}">
                                <i class="bi bi-{{ $network }}"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <h6 class="mb-3">{{ __('landing.footer.quick_links') }}</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="{{ $home }}#services">{{ __('landing.nav.services') }}</a></li>
                    <li><a href="{{ $home }}#how-it-works">{{ __('landing.nav.how_it_works') }}</a></li>
                    <li><a href="{{ localized_route('driver-application.create') }}">{{ __('landing.nav.become_driver') }}</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-6">
                <h6 class="mb-3">{{ __('landing.footer.legal') }}</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="{{ localized_route('pages.privacy') }}">{{ __('landing.footer.privacy') }}</a></li>
                    <li><a href="{{ localized_route('pages.terms') }}">{{ __('landing.footer.terms') }}</a></li>
                    <li><a href="{{ $home }}#contact">{{ __('landing.footer.contact') }}</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="mb-3">{{ __('landing.footer.download_app') }}</h6>
                <div style="max-width: 220px;">
                    <x-public.app-store-buttons class="flex-column" />
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="text-center small">
            <span>&copy; {{ now()->year }} {{ __('landing.common.app_name') }}. {{ __('landing.footer.copyright') }}</span>
        </div>
    </div>
</footer>
