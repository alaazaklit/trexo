<section id="hero" class="hero-section section pt-5">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <span class="hero-badge mb-4">
                    <i class="bi bi-geo-alt-fill text-brand"></i>
                    {{ __('landing.hero.badge') }}
                </span>

                <h1 class="hero-title mb-4">{{ __('landing.hero.title') }}</h1>
                <p class="hero-subtitle mb-4">{{ __('landing.hero.subtitle') }}</p>

                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="#download" class="btn btn-brand">
                        <i class="bi bi-download me-1"></i>
                        {{ __('landing.hero.cta_primary') }}
                    </a>
                    <a href="{{ localized_route('driver-application.create') }}" class="btn btn-brand-outline">
                        {{ __('landing.hero.cta_secondary') }}
                    </a>
                </div>

                <div id="download">
                    <x-public.app-store-buttons />
                </div>
            </div>

            <div class="col-lg-6">
                <x-public.phone-mockup
                    :image="asset('images/screenshots/home.jpg')"
                    :alt="__('landing.common.app_name')"
                />
            </div>
        </div>
    </div>
</section>
