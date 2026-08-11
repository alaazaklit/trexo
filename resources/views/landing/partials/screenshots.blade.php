@php
    $slides = [
        ['image' => 'login', 'label' => __('landing.screenshots.slides.1')],
        ['image' => 'home', 'label' => __('landing.screenshots.slides.2')],
        ['image' => 'map', 'label' => __('landing.screenshots.slides.3')],
        ['image' => 'schedule', 'label' => __('landing.screenshots.slides.4')],
        ['image' => 'schedule', 'label' => __('landing.screenshots.slides.5')],
    ];
@endphp

<section id="screenshots" class="section">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-5 reveal">
            <span class="eyebrow"><i class="bi bi-phone"></i> {{ __('landing.nav.screenshots') }}</span>
            <h2 class="mb-3">{{ __('landing.screenshots.title') }}</h2>
            <p class="mx-auto">{{ __('landing.screenshots.subtitle') }}</p>
        </div>

        <div id="screenshotsCarousel" class="carousel slide screenshots-carousel mx-auto" data-bs-ride="carousel">
            <div class="carousel-inner">
                @foreach ($slides as $index => $slide)
                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                        <div class="mx-auto" style="max-width: 260px;">
                            <x-public.phone-mockup
                                :image="asset('images/screenshots/'.$slide['image'].'.jpg')"
                                :alt="$slide['label']"
                            />
                            <p class="text-center fw-bold small text-muted-ad mt-3 mb-0">{{ $slide['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#screenshotsCarousel" data-bs-slide="prev">
                <span class="carousel-control-icon" aria-hidden="true"><i class="bi bi-chevron-{{ $dir === 'rtl' ? 'right' : 'left' }}"></i></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#screenshotsCarousel" data-bs-slide="next">
                <span class="carousel-control-icon" aria-hidden="true"><i class="bi bi-chevron-{{ $dir === 'rtl' ? 'left' : 'right' }}"></i></span>
            </button>

            <div class="carousel-indicators position-relative mt-4">
                @foreach ($slides as $index => $slide)
                    <button type="button" data-bs-target="#screenshotsCarousel" data-bs-slide-to="{{ $index }}" class="bg-brand {{ $index === 0 ? 'active' : '' }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>
