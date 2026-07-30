@extends('layouts.public')

@section('meta_title', __('pages.terms.title').' | '.__('landing.common.app_name'))

@section('content')
<section class="section">
    <div class="container" style="max-width: 760px;">
        <h1 class="fw-bold mb-2">{{ __('pages.terms.title') }}</h1>
        <p class="text-muted-ad mb-5">{{ __('pages.terms.updated_at', ['date' => now()->translatedFormat('Y-m-d')]) }}</p>

        @foreach (__('pages.terms.sections') as $section)
            <div class="mb-4">
                <h2 class="h5 fw-bold mb-2">{{ $section['heading'] }}</h2>
                <p class="text-muted-ad">{{ $section['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
