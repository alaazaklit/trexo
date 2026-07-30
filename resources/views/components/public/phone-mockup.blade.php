@props(['image' => null, 'alt' => ''])

<div class="phone-mockup">
    <span class="phone-notch"></span>
    <div class="phone-screen">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy">
        @else
            {{ $slot }}
        @endif
    </div>
</div>
