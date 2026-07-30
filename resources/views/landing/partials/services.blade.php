<section id="services" class="section bg-soft">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-5 reveal">
            <span class="eyebrow"><i class="bi bi-grid-fill"></i> {{ __('landing.nav.services') }}</span>
            <h2 class="mb-3">{{ __('landing.services.title') }}</h2>
            <p class="mx-auto">{{ __('landing.services.subtitle') }}</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <x-public.service-card
                    icon="taxi-front-fill"
                    :title="__('landing.services.taxi.title')"
                    :description="__('landing.services.taxi.description')"
                />
            </div>
            <div class="col-md-4">
                <x-public.service-card
                    icon="box-seam-fill"
                    :title="__('landing.services.delivery.title')"
                    :description="__('landing.services.delivery.description')"
                />
            </div>
            <div class="col-md-4">
                <x-public.service-card
                    icon="bus-front-fill"
                    :title="__('landing.services.school_bus.title')"
                    :description="__('landing.services.school_bus.description')"
                    :badge="__('landing.services.school_bus.badge')"
                />
            </div>
        </div>
    </div>
</section>
