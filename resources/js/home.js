// Homepage Swipers (spec §6.5/§10.6, M20/M26): hero slider + brands rail.
// carousels + brands rail. Swiper DOM lives inside wire:ignore and is
// initialized here, re-hooked on livewire:navigated (never
// DOMContentLoaded only).
// Autoplay follows the OWNER's motion level (owner fix, 2026-11-27):
// it runs at owner `full` and `lite` — hero every 10s, brands every 5s —
// and stops only when the owner set the level to `off`. The device
// downgrade (Save-Data / weak hardware / reduced motion) no longer
// kills autoplay: owners kept reporting "the images don't move" on
// ordinary phones whose browsers downgraded them to lite. Both pause
// on hover (pauseOnMouseEnter) and while the user interacts
// (disableOnInteraction stays false so autoplay resumes afterwards),
// and Swiper's Autoplay module pauses while the tab is hidden and
// resumes when it becomes visible again. The hero keeps its visible
// pause/play control and stops on focus.
import { Swiper } from 'swiper';
import { Autoplay, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';
import { ownerMotionLevel } from './motion/level.js';

function autoplayAllowed() {
    return ownerMotionLevel() !== 'off';
}

function initHero(root) {
    if (root.dataset.heroInit) return;
    root.dataset.heroInit = '1';
    // No pauseOnMouseEnter (owner fix, 2026-11-27): a mouse resting over
    // the hero kept it frozen for the whole visit, which read as "the
    // images never move". It advances every 10s, period; the visible
    // pause/play button remains for anyone who wants it stopped.
    const autoplay = autoplayAllowed()
        ? { delay: 10000, disableOnInteraction: false }
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
            if (!autoplayAllowed()) return;
            if (swiper.autoplay && swiper.autoplay.running) swiper.autoplay.stop();
            else if (swiper.autoplay) swiper.autoplay.start();
            sync();
        });
        sync();
        if (!autoplayAllowed()) toggle.hidden = true;
    }
}

function initBrandsRails() {
    document.querySelectorAll('[data-brands-rail]').forEach((el) => {
        if (el.dataset.brandsInit) return;
        el.dataset.brandsInit = '1';
        const slideCount = el.querySelectorAll('.swiper-slide').length;
        // Loop only when there are enough slides to fill the rail twice;
        // with fewer brands the rail is a plain swipeable strip.
        const loop = slideCount >= 6;
        const autoplay = autoplayAllowed() && slideCount > 2
            ? { delay: 5000, disableOnInteraction: false }
            : false;
        new Swiper(el, {
            modules: [Autoplay],
            slidesPerView: 'auto',
            spaceBetween: 12,
            loop,
            autoplay,
        });
    });
}

function initHome() {
    document.querySelectorAll('[data-hero]').forEach(initHero);
    initBrandsRails();
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
