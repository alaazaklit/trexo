import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

function initScrollReveal() {
    const targets = document.querySelectorAll('.reveal');
    if (!targets.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    targets.forEach((el) => observer.observe(el));
}

function initUploadFields() {
    document.querySelectorAll('.upload-field').forEach((field) => {
        const input = field.querySelector('input[type="file"]');
        const label = field.querySelector('.upload-file-name');
        if (!input) return;

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            field.classList.toggle('has-file', !!file);
            if (label) {
                label.textContent = file ? file.name : label.dataset.emptyText || '';
            }
        });
    });
}

function initDriverWizard() {
    const wizard = document.querySelector('[data-wizard]');
    if (!wizard) return;

    const panels = Array.from(wizard.querySelectorAll('.wizard-panel'));
    const steps = Array.from(wizard.querySelectorAll('.wizard-progress .wizard-step'));
    let current = Number(wizard.dataset.initialStep) || 1;

    function showStep(step, scroll = true) {
        panels.forEach((panel) => {
            panel.classList.toggle('active', Number(panel.dataset.step) === step);
        });

        steps.forEach((el) => {
            const stepNum = Number(el.dataset.step);
            el.classList.toggle('active', stepNum === step);
            el.classList.toggle('completed', stepNum < step);
        });

        current = step;
        if (scroll) {
            wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    wizard.querySelectorAll('.js-wizard-next').forEach((button) => {
        button.addEventListener('click', () => {
            const activePanel = panels.find((panel) => Number(panel.dataset.step) === current);
            if (activePanel) {
                const inputs = activePanel.querySelectorAll('input, select, textarea');
                let valid = true;
                inputs.forEach((input) => {
                    if (!input.checkValidity()) {
                        input.reportValidity();
                        valid = false;
                    }
                });
                if (!valid) return;
            }

            if (current < panels.length) {
                showStep(current + 1);
            }
        });
    });

    wizard.querySelectorAll('.js-wizard-back').forEach((button) => {
        button.addEventListener('click', () => {
            if (current > 1) {
                showStep(current - 1);
            }
        });
    });

    showStep(current, false);
}

function initSwiperCarousels() {
    document.querySelectorAll('.trexo-swiper').forEach((el) => {
        const wrap = el.closest('section') || el.parentElement;
        const prevEl = wrap.querySelector('.swiper-button-prev-custom');
        const nextEl = wrap.querySelector('.swiper-button-next-custom');
        const slidesLg = Number(el.dataset.slidesLg) || 3;
        const slidesXl = Number(el.dataset.slidesXl) || slidesLg;

        new Swiper(el, {
            modules: [Navigation, Pagination],
            slidesPerView: 1.12,
            centeredSlides: true,
            spaceBetween: 16,
            grabCursor: true,
            pagination: {
                el: el.querySelector('.swiper-pagination'),
                clickable: true,
            },
            navigation: prevEl && nextEl ? { prevEl, nextEl } : undefined,
            breakpoints: {
                576: { slidesPerView: 1.6, centeredSlides: true, spaceBetween: 20 },
                768: { slidesPerView: 2.3, centeredSlides: true, spaceBetween: 20 },
                992: { slidesPerView: slidesLg, centeredSlides: false, spaceBetween: 24 },
                1200: { slidesPerView: slidesXl, centeredSlides: false, spaceBetween: 24 },
            },
        });
    });
}

function initWhatsappModal() {
    const startBtn = document.getElementById('whatsappStartChat');
    if (!startBtn) return;

    startBtn.addEventListener('click', (event) => {
        event.preventDefault();

        const selected = document.querySelector('input[name="whatsappTopic"]:checked');
        const phone = startBtn.dataset.phone;
        if (!selected || !phone) return;

        const text = encodeURIComponent(selected.value);
        window.open(`https://wa.me/${phone}?text=${text}`, '_blank', 'noopener');

        const modalEl = startBtn.closest('.modal');
        const dismissBtn = modalEl && modalEl.querySelector('[data-bs-dismiss="modal"]');
        if (dismissBtn) {
            dismissBtn.click();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initScrollReveal();
    initUploadFields();
    initDriverWizard();
    initSwiperCarousels();
    initWhatsappModal();
});
