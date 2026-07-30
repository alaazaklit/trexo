@extends('layouts.public')

@section('meta_title', __('driver_application.title').' | '.__('landing.common.app_name'))
@section('meta_description', __('driver_application.subtitle'))

@php
    $step1Fields = ['full_name', 'mobile_number', 'whatsapp_number', 'email', 'city', 'service_type'];
    $step2Fields = ['national_id_number', 'driving_license_number', 'vehicle_type', 'vehicle_brand', 'vehicle_model', 'vehicle_year', 'plate_number'];
    $step3Fields = ['national_id_front', 'driving_license_file', 'vehicle_registration_file', 'personal_photo', 'vehicle_photo', 'confirmed_information_correct', 'agreed_terms'];

    $initialStep = 1;
    if ($errors->any()) {
        if ($errors->hasAny($step3Fields)) {
            $initialStep = 3;
        }
        if ($errors->hasAny($step2Fields)) {
            $initialStep = 2;
        }
        if ($errors->hasAny($step1Fields)) {
            $initialStep = 1;
        }
    }
@endphp

@section('content')
<section class="section">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-4">
            <span class="eyebrow"><i class="bi bi-steering-wheel"></i> {{ __('landing.become_driver.title') }}</span>
            <h1 class="mb-3">{{ __('driver_application.title') }}</h1>
            <p class="mx-auto">{{ __('driver_application.subtitle') }}</p>
        </div>

        <form method="POST" action="{{ localized_route('driver-application.store') }}" enctype="multipart/form-data" data-wizard data-initial-step="{{ $initialStep }}" novalidate class="mx-auto" style="max-width: 800px;">
            @csrf

            <div class="wizard-progress">
                @for ($i = 1; $i <= 3; $i++)
                    <div class="wizard-step {{ $i === $initialStep ? 'active' : '' }}" data-step="{{ $i }}">
                        <span class="wizard-dot">{{ $i }}</span>
                        <span class="wizard-label">{{ __('driver_application.steps.'.$i.'.title') }}</span>
                    </div>
                    @if ($i < 3)
                        <div class="wizard-bar"></div>
                    @endif
                @endfor
            </div>

            <div class="wizard-card">
                {{-- Step 1: Personal Information --}}
                <div class="wizard-panel {{ $initialStep === 1 ? 'active' : '' }}" data-step="1">
                    <h2 class="h5 fw-bold mb-1">{{ __('driver_application.steps.1.title') }}</h2>
                    <p class="text-muted-ad small mb-4">{{ __('driver_application.steps.1.description') }}</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.full_name') }}</label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control form-control-ad @error('full_name') is-invalid @enderror" required maxlength="150">
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.mobile_number') }}</label>
                            <input type="tel" name="mobile_number" value="{{ old('mobile_number') }}" class="form-control form-control-ad @error('mobile_number') is-invalid @enderror" required maxlength="30">
                            @error('mobile_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                {{ __('driver_application.fields.whatsapp_number') }}
                                <span class="text-muted-ad">({{ __('driver_application.optional') }})</span>
                            </label>
                            <input type="tel" name="whatsapp_number" value="{{ old('whatsapp_number') }}" class="form-control form-control-ad @error('whatsapp_number') is-invalid @enderror" maxlength="30">
                            @error('whatsapp_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">
                                {{ __('driver_application.fields.email') }}
                                <span class="text-muted-ad">({{ __('driver_application.optional') }})</span>
                            </label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-ad @error('email') is-invalid @enderror" maxlength="150">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.city') }}</label>
                            <select name="city" class="form-select form-select-ad @error('city') is-invalid @enderror" required>
                                <option value="" disabled {{ old('city') ? '' : 'selected' }}></option>
                                @foreach (__('driver_application.cities') as $value => $label)
                                    <option value="{{ $value }}" {{ old('city') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold d-block">{{ __('driver_application.fields.service_type') }}</label>
                            <div class="d-flex gap-2">
                                @foreach (__('driver_application.service_types') as $value => $label)
                                    <input type="radio" class="btn-check" name="service_type" id="service_type_{{ $value }}" value="{{ $value }}" {{ old('service_type') === $value ? 'checked' : '' }} required>
                                    <label class="btn btn-brand-outline flex-fill" for="service_type_{{ $value }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            @error('service_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-brand js-wizard-next">
                            {{ __('driver_application.buttons.next') }}
                            <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'left' : 'right' }} ms-1"></i>
                        </button>
                    </div>
                </div>

                {{-- Step 2: Driver Information --}}
                <div class="wizard-panel {{ $initialStep === 2 ? 'active' : '' }}" data-step="2">
                    <h2 class="h5 fw-bold mb-1">{{ __('driver_application.steps.2.title') }}</h2>
                    <p class="text-muted-ad small mb-4">{{ __('driver_application.steps.2.description') }}</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.national_id_number') }}</label>
                            <input type="text" name="national_id_number" value="{{ old('national_id_number') }}" class="form-control form-control-ad @error('national_id_number') is-invalid @enderror" required maxlength="60">
                            @error('national_id_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.driving_license_number') }}</label>
                            <input type="text" name="driving_license_number" value="{{ old('driving_license_number') }}" class="form-control form-control-ad @error('driving_license_number') is-invalid @enderror" required maxlength="60">
                            @error('driving_license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.vehicle_type') }}</label>
                            <select name="vehicle_type" class="form-select form-select-ad @error('vehicle_type') is-invalid @enderror" required>
                                <option value="" disabled {{ old('vehicle_type') ? '' : 'selected' }}></option>
                                @foreach (__('driver_application.vehicle_types') as $value => $label)
                                    <option value="{{ $value }}" {{ old('vehicle_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.vehicle_year') }}</label>
                            <input type="number" name="vehicle_year" value="{{ old('vehicle_year') }}" class="form-control form-control-ad @error('vehicle_year') is-invalid @enderror" required min="1980" max="{{ now()->year + 1 }}">
                            @error('vehicle_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.vehicle_brand') }}</label>
                            <input type="text" name="vehicle_brand" value="{{ old('vehicle_brand') }}" class="form-control form-control-ad @error('vehicle_brand') is-invalid @enderror" required maxlength="60">
                            @error('vehicle_brand') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.vehicle_model') }}</label>
                            <input type="text" name="vehicle_model" value="{{ old('vehicle_model') }}" class="form-control form-control-ad @error('vehicle_model') is-invalid @enderror" required maxlength="60">
                            @error('vehicle_model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('driver_application.fields.plate_number') }}</label>
                            <input type="text" name="plate_number" value="{{ old('plate_number') }}" class="form-control form-control-ad @error('plate_number') is-invalid @enderror" required maxlength="30">
                            @error('plate_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-brand-outline js-wizard-back">
                            <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'right' : 'left' }} me-1"></i>
                            {{ __('driver_application.buttons.back') }}
                        </button>
                        <button type="button" class="btn btn-brand js-wizard-next">
                            {{ __('driver_application.buttons.next') }}
                            <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'left' : 'right' }} ms-1"></i>
                        </button>
                    </div>
                </div>

                {{-- Step 3: Upload Documents --}}
                <div class="wizard-panel {{ $initialStep === 3 ? 'active' : '' }}" data-step="3">
                    <h2 class="h5 fw-bold mb-1">{{ __('driver_application.steps.3.title') }}</h2>
                    <p class="text-muted-ad small mb-4">{{ __('driver_application.steps.3.description') }}</p>

                    <div class="row g-3">
                        @foreach ([
                            'national_id_front' => true,
                            'driving_license_file' => true,
                            'vehicle_registration_file' => true,
                            'personal_photo' => false,
                            'vehicle_photo' => false,
                        ] as $field => $required)
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">
                                    {{ __('driver_application.fields.'.$field) }}
                                    @if (!$required)
                                        <span class="text-muted-ad">({{ __('driver_application.optional') }})</span>
                                    @endif
                                </label>
                                <label class="upload-field d-flex align-items-center gap-3 mb-0 @error($field) border-danger @enderror" for="{{ $field }}">
                                    <span class="icon-badge"><i class="bi bi-cloud-arrow-up-fill"></i></span>
                                    <span class="flex-grow-1 small">
                                        <span class="upload-file-name d-block fw-bold" data-empty-text="{{ __('driver_application.upload.no_file') }}">{{ __('driver_application.upload.no_file') }}</span>
                                        <span class="text-muted-ad">{{ __('driver_application.upload.hint') }}</span>
                                    </span>
                                    <span class="btn btn-sm btn-brand-outline">{{ __('driver_application.upload.choose_file') }}</span>
                                    <input type="file" name="{{ $field }}" id="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf" {{ $required ? 'required' : '' }}>
                                </label>
                                @error($field) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 d-flex flex-column gap-2">
                        <div class="form-check">
                            <input type="checkbox" name="confirmed_information_correct" value="1" id="confirmed_information_correct" class="form-check-input @error('confirmed_information_correct') is-invalid @enderror" required {{ old('confirmed_information_correct') ? 'checked' : '' }}>
                            <label class="form-check-label small" for="confirmed_information_correct">
                                {{ __('driver_application.agreements.information_correct') }}
                            </label>
                            @error('confirmed_information_correct') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="agreed_terms" value="1" id="agreed_terms" class="form-check-input @error('agreed_terms') is-invalid @enderror" required {{ old('agreed_terms') ? 'checked' : '' }}>
                            <label class="form-check-label small" for="agreed_terms">
                                {!! __('driver_application.agreements.terms_and_privacy', [
                                    'terms' => '<a href="'.localized_route('pages.terms').'" target="_blank">'.__('driver_application.agreements.terms_link').'</a>',
                                    'privacy' => '<a href="'.localized_route('pages.privacy').'" target="_blank">'.__('driver_application.agreements.privacy_link').'</a>',
                                ]) !!}
                            </label>
                            @error('agreed_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-brand-outline js-wizard-back">
                            <i class="bi bi-arrow-{{ $dir === 'rtl' ? 'right' : 'left' }} me-1"></i>
                            {{ __('driver_application.buttons.back') }}
                        </button>
                        <button type="submit" class="btn btn-brand">
                            {{ __('driver_application.buttons.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection
