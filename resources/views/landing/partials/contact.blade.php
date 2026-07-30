<section id="contact" class="section">
    <div class="container">
        <div class="section-heading text-center mx-auto mb-5 reveal">
            <span class="eyebrow"><i class="bi bi-envelope-fill"></i> {{ __('landing.nav.contact') }}</span>
            <h2 class="mb-3">{{ __('landing.contact.title') }}</h2>
            <p class="mx-auto">{{ __('landing.contact.subtitle') }}</p>
        </div>

        <div class="row g-4 g-lg-5">
            <div class="col-lg-5">
                <div class="d-flex flex-column gap-4">
                    <div class="contact-info-item">
                        <span class="icon-badge"><i class="bi bi-envelope-fill"></i></span>
                        <div>
                            <div class="small text-muted-ad">{{ __('landing.contact.info.email') }}</div>
                            <div class="fw-bold">{{ config('marketing.contact_email') }}</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <span class="icon-badge"><i class="bi bi-telephone-fill"></i></span>
                        <div>
                            <div class="small text-muted-ad">{{ __('landing.contact.info.phone') }}</div>
                            <div class="fw-bold" dir="ltr">{{ config('marketing.contact_phone') }}</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <span class="icon-badge"><i class="bi bi-geo-alt-fill"></i></span>
                        <div>
                            <div class="small text-muted-ad">{{ __('landing.contact.info.location') }}</div>
                            <div class="fw-bold">{{ __('landing.contact.info.location_value') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                @if (session('contact_success'))
                    <div class="alert alert-success rounded-4">{{ __('landing.contact.success') }}</div>
                @endif

                <form method="POST" action="{{ localized_route('contact.store') }}" class="card-ad p-4">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('landing.contact.form.name') }}</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-ad @error('name') is-invalid @enderror" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('landing.contact.form.phone') }}</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control form-control-ad @error('phone') is-invalid @enderror">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">{{ __('landing.contact.form.email') }}</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-ad @error('email') is-invalid @enderror" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">{{ __('landing.contact.form.message') }}</label>
                            <textarea name="message" rows="4" class="form-control form-control-ad @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                            @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-brand w-100">{{ __('landing.contact.form.submit') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
