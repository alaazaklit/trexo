@php
    $home = localized_route('landing.home');
@endphp
<nav class="navbar navbar-expand-lg ad-navbar sticky-top py-3">
    <div class="container">
        <a class="navbar-brand" href="{{ $home }}">
            <img src="{{ asset('images/logo-x.png') }}" alt="{{ __('landing.common.app_name') }}" class="brand-logo">
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adNavbar" aria-controls="adNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adNavbar">
            <ul class="navbar-nav mx-lg-auto my-3 my-lg-0 gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="{{ $home }}#services">{{ __('landing.nav.services') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $home }}#how-it-works">{{ __('landing.nav.how_it_works') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $home }}#screenshots">{{ __('landing.nav.screenshots') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $home }}#become-driver">{{ __('landing.nav.become_driver') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $home }}#faq">{{ __('landing.nav.faq') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $home }}#contact">{{ __('landing.nav.contact') }}</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <x-public.language-switcher />

                <a href="{{ localized_route('driver-application.create') }}" class="btn btn-brand-outline btn-sm">
                    {{ __('landing.nav.become_driver') }}
                </a>
                <a href="{{ $home }}#hero" class="btn btn-brand btn-sm">
                    {{ __('landing.nav.download_app') }}
                </a>
            </div>
        </div>
    </div>
</nav>
