@extends('layouts.public')

@section('meta_title', __('pages.privacy.title').' | '.__('landing.common.app_name'))

@section('content')
<section class="section">
    <div class="container" style="max-width: 760px;">
        <h1 class="fw-bold mb-2">{{ __('pages.privacy.title') }}</h1>
        <p class="text-muted-ad mb-5">{{ __('pages.privacy.updated_at', ['date' => now()->translatedFormat('Y-m-d')]) }}</p>

        @foreach (__('pages.privacy.sections') as $section)
            <div class="mb-4">
                <h2 class="h5 fw-bold mb-2">{{ $section['heading'] }}</h2>
                <p class="text-muted-ad">{{ $section['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
