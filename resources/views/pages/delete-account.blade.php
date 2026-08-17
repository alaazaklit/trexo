@extends('layouts.public')
@section('meta_title', __('pages.delete_account.title').' | '.__('landing.common.app_name'))
@section('content')
<section class="section">
    <div class="container" style="max-width: 560px;">
        <h1 class="fw-bold mb-4">{{ __('pages.delete_account.title') }}</h1>

        @if (session('deleted'))
            <div class="alert alert-success">
                <h2 class="h6 fw-bold mb-2">{{ __('pages.delete_account.deleted_title') }}</h2>
                <p class="mb-0">{{ __('pages.delete_account.deleted_body') }}</p>
            </div>
        @else
            <p class="text-muted-ad mb-3">{{ __('pages.delete_account.intro') }}</p>
            <div class="alert alert-warning">{{ __('pages.delete_account.warning') }}</div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ localized_route('pages.delete-account.otp') }}" class="mb-4">
                @csrf
                <label class="form-label small fw-bold">{{ __('pages.delete_account.phone_label') }}</label>
                <div class="input-group">
                    <input type="tel"
                           name="phone"
                           value="{{ old('phone', session('otp_sent_phone')) }}"
                           placeholder="{{ __('pages.delete_account.phone_placeholder') }}"
                           class="form-control form-control-ad @error('phone') is-invalid @enderror"
                           required
                           maxlength="30">
                    <button type="submit" class="btn btn-outline-brand">
                        {{ session('otp_sent_phone') ? __('pages.delete_account.resend') : __('pages.delete_account.send_code') }}
                    </button>
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </form>

            @if (session('otp_sent_phone'))
                <form method="POST" action="{{ localized_route('pages.delete-account.confirm') }}">
                    @csrf
                    <input type="hidden" name="phone" value="{{ session('otp_sent_phone') }}">

                    <label class="form-label small fw-bold">{{ __('pages.delete_account.otp_label') }}</label>
                    <input type="text"
                           inputmode="numeric"
                           name="otp"
                           placeholder="{{ __('pages.delete_account.otp_placeholder') }}"
                           class="form-control form-control-ad mb-1 @error('otp') is-invalid @enderror"
                           maxlength="6"
                           required>

                    @error('otp')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror

                    <p class="text-muted-ad small mt-2">
                        {{ __('pages.delete_account.otp_hint', ['phone' => session('otp_sent_phone')]) }}
                    </p>

                    <button type="submit" class="btn btn-danger mt-2">
                        {{ __('pages.delete_account.confirm_button') }}
                    </button>
                </form>
            @endif
        @endif
    </div>
</section>
@endsection
