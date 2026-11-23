// Storefront entry point. Motion modules (spec 11.5) are imported here;
// no inline script blobs live in Blade templates.
import './motion/level.js';
import './motion/reveal.js';
import './motion/live.js';
import './motion/fx.js';
import './motion/fly-to-cart.js';
import './motion/sticky-bar.js';
import './motion/header.js';
import './motion/switch.js';
import './motion/splash.js';
// Auto-animate small lists with stable wire:key (spec 11.9).
import autoAnimate from '@formkit/auto-animate';
function initAutoAnimate() {
    document.querySelectorAll('[data-auto-animate]').forEach((el) => {
        if (el.dataset.autoAnimateInit) return;
        el.dataset.autoAnimateInit = '1';
        autoAnimate(el, { duration: 200, easing: 'ease-out' });
    });
}
document.addEventListener('DOMContentLoaded', initAutoAnimate);
document.addEventListener('livewire:navigated', initAutoAnimate);

// Product gallery (Swiper) is loaded lazily only on pages that have one.
if (document.querySelector('[data-gallery]')) {
    import('./gallery.js');
}
document.addEventListener('livewire:navigated', () => {
    if (document.querySelector('[data-gallery]')) {
        import('./gallery.js');
    }
});

// GA4 module loads only when the owner configured a measurement ID
// (spec §9A); without the meta tag nothing analytics-related is fetched.
if (document.querySelector('meta[name="ga4-id"]')) {
    import('./analytics.js');
}
document.addEventListener('livewire:navigated', () => {
    if (document.querySelector('meta[name="ga4-id"]')) {
        import('./analytics.js');
    }
});

// Homepage hero + product carousels load lazily only where present.
if (document.querySelector('[data-hero], [data-product-carousel]')) {
    import('./home.js');
}
document.addEventListener('livewire:navigated', () => {
    if (document.querySelector('[data-hero], [data-product-carousel]')) {
        import('./home.js');
    }
});
