@props(['icon', 'title', 'description'])

<div class="card-ad p-4 reveal">
    <span class="icon-badge mb-3"><i class="bi bi-{{ $icon }}"></i></span>
    <h3 class="h6 fw-bold mb-2">{{ $title }}</h3>
    <p class="text-muted-ad mb-0 small">{{ $description }}</p>
</div>
