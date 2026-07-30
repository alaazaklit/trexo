<section id="faq" class="section bg-soft">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-5 reveal">
            <span class="eyebrow"><i class="bi bi-question-circle-fill"></i> {{ __('landing.nav.faq') }}</span>
            <h2 class="mb-3">{{ __('landing.faq.title') }}</h2>
            <p class="mx-auto">{{ __('landing.faq.subtitle') }}</p>
        </div>

        <div class="accordion accordion-ad mx-auto reveal" id="faqAccordion" style="max-width: 760px;">
            @foreach (__('landing.faq.items') as $index => $item)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="faqHeading{{ $index }}">
                        <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="faqCollapse{{ $index }}">
                            {{ $item['question'] }}
                        </button>
                    </h3>
                    <div id="faqCollapse{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="faqHeading{{ $index }}" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted-ad">
                            {{ $item['answer'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
