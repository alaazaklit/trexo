@props(['class' => ''])

<div class="d-flex flex-wrap gap-2 {{ $class }}">
    <a href="{{ config('marketing.app_store_url') }}" class="store-badge">
        <i class="bi bi-apple"></i>
        <span>
            <small>{{ __('landing.hero.store_apple') }}</small>
        </span>
    </a>
    <a href="{{ config('marketing.play_store_url') }}" class="store-badge">
        <i class="bi bi-google-play"></i>
        <span>
            <small>{{ __('landing.hero.store_google') }}</small>
        </span>
    </a>
</div>
