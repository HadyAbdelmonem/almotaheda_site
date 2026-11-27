// Product gallery (spec §6.2, §11.9): Swiper lives inside wire:ignore and is
// initialized here (never from DOMContentLoaded alone — wire:navigate does
// not re-fire it, so we also hook livewire:navigated). Only the modules
// actually used are imported: Autoplay, Thumbs, Zoom, Pagination.
// Autoplay (owner pass 2, 2026-11-23; gate changed 2026-11-27): every
// 5s, gated exactly like the hero/brands autoplay in home.js — it
// follows the OWNER's motion level and runs unless the owner set it to
// `off` (the device downgrade to lite no longer stops it), only with
// more than one image, pausing on hover and while the user interacts
// (disableOnInteraction stays false so it resumes afterwards).
import { Swiper } from 'swiper';
import { Autoplay, Navigation, Pagination, Thumbs, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/zoom';

function autoplayAllowed() {
    return (document.documentElement.dataset.motionOwner || 'full') !== 'off';
}

function initGalleries() {
    document.querySelectorAll('[data-gallery]').forEach((root) => {
        if (root.dataset.galleryInit) return;
        root.dataset.galleryInit = '1';
        const thumbsEl = root.querySelector('[data-gallery-thumbs]');
        let thumbs = null;
        if (thumbsEl) {
            thumbs = new Swiper(thumbsEl, {
                modules: [Thumbs],
                slidesPerView: 4,
                spaceBetween: 8,
                watchSlidesProgress: true,
            });
        }
        const mainEl = root.querySelector('[data-gallery-main]');
        const slideCount = mainEl.querySelectorAll('.swiper-slide').length;
        const autoplay = autoplayAllowed() && slideCount > 1
            ? { delay: 5000, disableOnInteraction: false, pauseOnMouseEnter: true }
            : false;
        const prevEl = root.querySelector('[data-gallery-prev]');
        const nextEl = root.querySelector('[data-gallery-next]');
        new Swiper(mainEl, {
            modules: [Autoplay, Navigation, Thumbs, Zoom, Pagination],
            zoom: { maxRatio: 2 },
            loop: slideCount > 1,
            autoplay,
            navigation: prevEl && nextEl ? { prevEl, nextEl } : false,
            pagination: { el: root.querySelector('[data-gallery-pagination]'), clickable: true },
            thumbs: thumbs ? { swiper: thumbs } : undefined,
        });
    });
}

document.addEventListener('DOMContentLoaded', initGalleries);
document.addEventListener('livewire:navigated', initGalleries);
