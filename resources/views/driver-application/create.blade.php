@extends('layouts.public-minimal')

@section('meta_title', __('driver_application.title').' | '.__('landing.common.app_name'))
@section('meta_description', __('driver_application.subtitle'))

@php
    $slides = [
        ['image' => '1', 'label' => __('landing.screenshots.slides.1')],
        ['image' => '2', 'label' => __('landing.screenshots.slides.2')],
        ['image' => '3', 'label' => __('landing.screenshots.slides.3')],
        ['image' => '4', 'label' => __('landing.screenshots.slides.4')],
         ['image' => '5', 'label' => __('landing.screenshots.slides.5')],
    ];
@endphp

<style>
    .service-type-card {
        border: 2px solid var(--ad-border);
        border-radius: var(--ad-radius-md);
        padding: 0.85rem 1rem;
        font-weight: 600;
        color: var(--ad-dark);
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease, color .15s ease;
    }

    .service-type-card i {
        font-size: 1.1rem;
    }

    .btn-check:checked + .service-type-card {
        border-color: var(--ad-primary);
        background-color: var(--ad-primary-light);
        color: var(--ad-primary-dark);
    }

    @media (max-width: 767.98px) {
        .submit-btn {
            width: 100%;
        }
    }
</style>

@section('content')

<section class="section">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-4">
            <span class="eyebrow"><i class="bi bi-steering-wheel"></i> {{ __('landing.become_driver.title') }}</span>
            <h1 class="mb-3">{{ __('driver_application.title') }}</h1>
            <p class="mx-auto">{{ __('driver_application.subtitle') }}</p>
        </div>

        <form method="POST"
              action="{{ localized_route('driver-application.store') }}"
              data-simple-validate
              class="mx-auto"
              style="max-width: 800px;">

            @csrf

            <div class="wizard-card">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">
                            {{ __('driver_application.fields.full_name') }}
                        </label>

                        <input type="text"
                               name="full_name"
                               value="{{ old('full_name') }}"
                               data-required-message="{{ __('driver_application.errors.required') }}"
                               class="form-control form-control-ad @error('full_name') is-invalid @enderror"
                               required
                               maxlength="150">

                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">
                            {{ __('driver_application.fields.mobile_number') }}
                        </label>

                        <input type="tel"
                               name="mobile_number"
                               value="{{ old('mobile_number') }}"
                               data-required-message="{{ __('driver_application.errors.required') }}"
                               class="form-control form-control-ad @error('mobile_number') is-invalid @enderror"
                               required
                               maxlength="30">

                        @error('mobile_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">
                            {{ __('driver_application.fields.city') }}
                        </label>

                        <select name="city"
                                class="form-select form-select-ad @error('city') is-invalid @enderror"
                                required>

                            @foreach (__('driver_application.cities') as $value => $label)
                                <option value="{{ $value }}"
                                    {{ old('city', 'sidon') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        @error('city')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">
                            {{ __('driver_application.fields.plate_number') }}
                        </label>

                        <input type="text"
                               name="plate_number"
                               value="{{ old('plate_number') }}"
                               placeholder="{{ __('driver_application.fields.plate_number_placeholder') }}"
                               pattern="[A-Za-z]{1,3}[\s\-]?\d{3,6}"
                               title="{{ __('driver_application.fields.plate_number_format_error') }}"
                               data-required-message="{{ __('driver_application.errors.required') }}"
                               data-pattern-message="{{ __('driver_application.fields.plate_number_format_error') }}"
                               class="form-control form-control-ad @error('plate_number') is-invalid @enderror"
                               required
                               maxlength="30">

                        @error('plate_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-brand submit-btn">
                        {{ __('driver_application.buttons.submit') }}
                    </button>
                </div>

            </div>
        </form>

    </div>
</section>

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('form[data-simple-validate]');
        if (!form) return;

        form.setAttribute('novalidate', 'novalidate');

        var fields = Array.prototype.slice.call(form.querySelectorAll('[required], [pattern]'));

        function messageFor(field) {
            if (field.validity.patternMismatch && field.dataset.patternMessage) {
                return field.dataset.patternMessage;
            }
            if (field.validity.valueMissing && field.dataset.requiredMessage) {
                return field.dataset.requiredMessage;
            }
            return field.validationMessage;
        }

        function showError(field) {
            field.classList.add('is-invalid');

            var feedback = field.nextElementSibling;
            if (!feedback || feedback.className.indexOf('invalid-feedback') === -1) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.insertBefore(feedback, field.nextSibling);
            }

            feedback.textContent = messageFor(field);
            feedback.style.display = 'block';
        }

        function clearError(field) {
            field.classList.remove('is-invalid');

            var feedback = field.nextElementSibling;
            if (feedback && feedback.className.indexOf('invalid-feedback') !== -1) {
                feedback.style.display = 'none';
            }
        }

        for (var i = 0; i < fields.length; i++) {
            fields[i].addEventListener('input', function () {
                if (this.checkValidity()) clearError(this);
            });
            fields[i].addEventListener('blur', function () {
                if (!this.checkValidity()) showError(this);
            });
        }

        form.addEventListener('submit', function (event) {
            var firstInvalid = null;

            for (var i = 0; i < fields.length; i++) {
                var field = fields[i];
                if (!field.checkValidity()) {
                    showError(field);
                    firstInvalid = firstInvalid || field;
                } else {
                    clearError(field);
                }
            }

            if (firstInvalid) {
                event.preventDefault();
                event.stopPropagation();
                firstInvalid.focus();
                if (firstInvalid.scrollIntoView) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    })();
</script>
@endpush

{{-- Screenshots showcase: proof the app is real and working --}}
<section id="screenshots" class="section pt-0">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-5">
            <span class="eyebrow"><i class="bi bi-phone"></i> {{ __('landing.nav.screenshots') }}</span>
            <h2 class="mb-3">{{ __('driver_application.showcase.title') }}</h2>
            <p class="mx-auto">{{ __('driver_application.showcase.subtitle') }}</p>
        </div>

        <div id="screenshotsCarousel" class="carousel slide screenshots-carousel mx-auto" data-bs-ride="carousel">
            <div class="carousel-inner">
                @foreach ($slides as $index => $slide)
                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                        <div class="mx-auto" style="max-width: 260px;">
                            <x-public.phone-mockup
                                :image="asset('images/screenshots/'.$slide['image'].'.png')"
                                :alt="$slide['label']"
                            />
                            <!-- <p class="text-center fw-bold small text-muted-ad mt-3 mb-0">{{ $slide['label'] }}</p> -->
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
@endsection
