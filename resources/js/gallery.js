// Product gallery (spec §6.2, §11.9): Swiper lives inside wire:ignore and is
// initialized here (never from DOMContentLoaded alone — wire:navigate does
// not re-fire it, so we also hook livewire:navigated). Only the modules
// actually used are imported: Autoplay, Thumbs, Zoom, Pagination.
// Autoplay (owner pass 2, 2026-11-23): every 5s, gated exactly like the
// hero/brands autoplay in home.js — only at motion level `full` (which
// already excludes prefers-reduced-motion via the level downgrade), only
// with more than one image, pausing on hover and while the user
// interacts (disableOnInteraction stays false so it resumes afterwards).
import { Swiper } from 'swiper';
import { Autoplay, Navigation, Pagination, Thumbs, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/zoom';

function motionIsFull() {
    return document.documentElement.dataset.motion === 'full';
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
        const autoplay = motionIsFull() && slideCount > 1
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
