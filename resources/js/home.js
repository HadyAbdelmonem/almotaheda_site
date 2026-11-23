// Homepage Swipers (spec §6.5/§10.6, M20/M26): hero slider + product
// carousels. Swiper DOM lives inside wire:ignore and is initialized
// here, re-hooked on livewire:navigated (never DOMContentLoaded only).
// Hero autoplay runs ONLY at motion level `full`, >= 5s, with a
// visible pause/play control; it stops on focus/interaction.
import { Swiper } from 'swiper';
import { Autoplay, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';

function motionIsFull() {
    return document.documentElement.dataset.motion === 'full';
}

function initHero(root) {
    if (root.dataset.heroInit) return;
    root.dataset.heroInit = '1';
    const autoplay = motionIsFull()
        ? { delay: 5000, disableOnInteraction: true, pauseOnMouseEnter: true }
        : false;
    const swiper = new Swiper(root.querySelector('[data-hero-main]'), {
        modules: [Autoplay, Pagination],
        autoplay,
        loop: root.querySelectorAll('.swiper-slide').length > 1,
        pagination: { el: root.querySelector('[data-hero-pagination]'), clickable: true },
        on: {
            focusIn(s) { if (s.autoplay) s.autoplay.stop(); },
        },
    });
    const toggle = root.querySelector('[data-hero-toggle]');
    if (toggle) {
        const labelPlay = toggle.dataset.labelPlay || '';
        const labelPause = toggle.dataset.labelPause || '';
        const sync = () => {
            const running = !!(swiper.autoplay && swiper.autoplay.running);
            toggle.setAttribute('aria-label', running ? labelPause : labelPlay);
            toggle.dataset.state = running ? 'playing' : 'paused';
            const icon = toggle.querySelector('[data-hero-toggle-icon]');
            if (icon) icon.textContent = running ? '❚❚' : '▶';
        };
        toggle.addEventListener('click', () => {
            if (!motionIsFull()) return;
            if (swiper.autoplay && swiper.autoplay.running) swiper.autoplay.stop();
            else if (swiper.autoplay) swiper.autoplay.start();
            sync();
        });
        sync();
        if (!motionIsFull()) toggle.hidden = true;
    }
}

function initProductCarousels() {
    document.querySelectorAll('[data-product-carousel]').forEach((el) => {
        if (el.dataset.carouselInit) return;
        el.dataset.carouselInit = '1';
        new Swiper(el, {
            slidesPerView: 2.2,
            spaceBetween: 12,
            breakpoints: {
                768: { slidesPerView: 3 },
                1024: { slidesPerView: 4 },
                1280: { slidesPerView: 5 },
            },
        });
    });
}

function initHome() {
    document.querySelectorAll('[data-hero]').forEach(initHero);
    initProductCarousels();
}

// M21: the hero circuit overlay starts only after the window `load`
// event (never before, never blocking the LCP slide), fading in via
// opacity only. In lite/off the circuit's pulses are disabled by CSS;
// the static panel may still fade in as a decorative layer.
window.addEventListener('load', () => {
    document.querySelectorAll('[data-hero-ambient]').forEach((el) => {
        el.style.opacity = '0.45';
    });
});

document.addEventListener('DOMContentLoaded', initHome);
document.addEventListener('livewire:navigated', initHome);
