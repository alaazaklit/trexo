@php
    $steps = ['download', 'create_account', 'book', 'driver_accepts', 'complete_trip'];
@endphp

<section id="how-it-works" class="section bg-soft">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-5 reveal">
            <span class="eyebrow"><i class="bi bi-list-ol"></i> {{ __('landing.nav.how_it_works') }}</span>
            <h2 class="mb-3">{{ __('landing.how_it_works.title') }}</h2>
            <p class="mx-auto">{{ __('landing.how_it_works.subtitle') }}</p>
        </div>

        <div class="trexo-swiper reveal" dir="{{ $dir }}" data-slides-lg="4" data-slides-xl="5">
            <div class="swiper-wrapper">
                @foreach ($steps as $index => $step)
                    <div class="swiper-slide">
                        <div class="card-ad step-slide-card p-4 text-center">
                            <span class="step-number mx-auto">{{ $index + 1 }}</span>
                            <h3 class="h6 fw-bold mt-3 mb-2">{{ __('landing.how_it_works.steps.'.$step.'.title') }}</h3>
                            <p class="text-muted-ad small mb-0">{{ __('landing.how_it_works.steps.'.$step.'.description') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="swiper-pagination"></div>
        </div>
        <div class="trexo-swiper-nav d-none d-lg-flex justify-content-center gap-3 mt-4">
            <button type="button" class="swiper-nav-btn swiper-button-prev-custom" aria-label="{{ __('driver_application.buttons.back') }}">
                <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'right' : 'left' }}"></i>
            </button>
            <button type="button" class="swiper-nav-btn swiper-button-next-custom" aria-label="{{ __('driver_application.buttons.next') }}">
                <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'left' : 'right' }}"></i>
            </button>
        </div>
    </div>
</section>
