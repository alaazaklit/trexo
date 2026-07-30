@extends('layouts.public')

@section('meta_title', __('driver_application.success.title').' | '.__('landing.common.app_name'))

@section('content')
<section class="section">
    <div class="container text-center" style="max-width: 560px;">
        <span class="success-check mb-4">
            <i class="bi bi-check-lg"></i>
        </span>
        <h1 class="h3 fw-bold mb-3">{{ __('driver_application.success.title') }}</h1>
        <p class="text-muted-ad mb-4">{{ __('driver_application.success.message') }}</p>
        <a href="{{ localized_route('landing.home') }}" class="btn btn-brand">
            {{ __('driver_application.success.back_home') }}
        </a>
    </div>
</section>
@endsection
