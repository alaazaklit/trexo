@props(['icon', 'title', 'description', 'badge' => null])

<div class="card-ad p-4 p-lg-5 reveal">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <span class="icon-badge"><i class="bi bi-{{ $icon }}"></i></span>
        @if ($badge)
            <span class="badge-coming-soon">{{ $badge }}</span>
        @endif
    </div>
    <h3 class="h5 fw-bold mb-2">{{ $title }}</h3>
    <p class="text-muted-ad mb-0">{{ $description }}</p>
</div>
