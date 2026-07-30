<section id="become-driver" class="section">
    <div class="container">
        <div class="become-driver-section p-4 p-lg-5 reveal">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <h2 class="fw-bold mb-3">{{ __('landing.become_driver.title') }}</h2>
                    <p class="mb-4 opacity-90">{{ __('landing.become_driver.subtitle') }}</p>

                    <ul class="list-unstyled benefit-list mb-4">
                        @foreach (__('landing.become_driver.benefits') as $benefit)
                            <li>
                                <i class="bi bi-check-circle-fill"></i>
                                <span>{{ $benefit }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ localized_route('driver-application.create') }}" class="btn btn-light fw-bold rounded-pill px-4 py-2">
                        {{ __('landing.become_driver.cta') }}
                        <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'left' : 'right' }} ms-1"></i>
                    </a>
                </div>
                <div class="col-lg-5 text-center">
                    <i class="bi bi-car-front" style="font-size: 8rem; opacity: 0.35;"></i>
                </div>
            </div>
        </div>
    </div>
</section>
