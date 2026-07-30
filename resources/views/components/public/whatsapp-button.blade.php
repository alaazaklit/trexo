@php
    $digits = preg_replace('/\D+/', '', config('marketing.contact_whatsapp'));
    $options = __('landing.contact.whatsapp.options');
@endphp

@if ($digits)
    <button
        type="button"
        class="whatsapp-fab"
        data-bs-toggle="modal"
        data-bs-target="#whatsappModal"
        aria-label="{{ __('landing.contact.info.phone') }} — WhatsApp"
    >
        <i class="bi bi-whatsapp"></i>
    </button>

    <div class="modal fade" id="whatsappModal" tabindex="-1" aria-labelledby="whatsappModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="whatsappModalLabel">{{ __('landing.contact.whatsapp.modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted-ad mb-3">{{ __('landing.contact.whatsapp.modal_subtitle') }}</p>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($options as $key => $option)
                            <div class="form-check whatsapp-option">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="whatsappTopic"
                                    id="whatsappTopic-{{ $key }}"
                                    value="{{ $option['message'] }}"
                                    {{ $loop->first ? 'checked' : '' }}
                                >
                                <label class="form-check-label" for="whatsappTopic-{{ $key }}">
                                    {{ $option['label'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-brand-outline" data-bs-dismiss="modal">
                        {{ __('landing.contact.whatsapp.cancel') }}
                    </button>
                    <a
                        href="#"
                        id="whatsappStartChat"
                        class="btn btn-brand"
                        data-phone="{{ $digits }}"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="bi bi-whatsapp me-1"></i> {{ __('landing.contact.whatsapp.submit') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endif
