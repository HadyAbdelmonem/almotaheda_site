// Product gallery (spec §6.2, §11.9): Swiper lives inside wire:ignore and is
// initialized here (never from DOMContentLoaded alone — wire:navigate does
// not re-fire it, so we also hook livewire:navigated). Only the modules
// actually used are imported: Thumbs, Zoom, Pagination.
import { Swiper } from 'swiper';
import { Pagination, Thumbs, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/zoom';

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
        new Swiper(root.querySelector('[data-gallery-main]'), {
            modules: [Thumbs, Zoom, Pagination],
            zoom: { maxRatio: 2 },
            pagination: { el: root.querySelector('[data-gallery-pagination]'), clickable: true },
            thumbs: thumbs ? { swiper: thumbs } : undefined,
        });
    });
}

document.addEventListener('DOMContentLoaded', initGalleries);
document.addEventListener('livewire:navigated', initGalleries);
