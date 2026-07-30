<div class="dropdown lang-switcher">
    <button class="btn btn-sm dropdown-toggle d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-globe2"></i>
        {{ $currentLocaleMeta['native'] }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach (config('localization.locales') as $code => $meta)
            <li>
                <a class="dropdown-item d-flex justify-content-between {{ $code === $currentLocale ? 'active' : '' }}" href="{{ alternate_locale_url($code) }}">
                    <span>{{ $meta['name'] }}</span>
                    @if ($code === $currentLocale)
                        <i class="bi bi-check2"></i>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
