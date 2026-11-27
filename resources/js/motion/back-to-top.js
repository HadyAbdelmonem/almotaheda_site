// Back-to-top float (owner request, 2026-11-23): the button is rendered
// by components/back-to-top.blade.php, stacked above the WhatsApp float.
// It stays hidden until the page is scrolled past ~550px, then fades and
// slides in (CSS in app.css, [data-back-to-top]). Click glides to the
// top with a manual requestAnimationFrame animation (owner fix,
// 2026-11-27): scrollTo({behavior:'smooth'}) still lands instantly in
// browsers whose own smooth-scrolling is turned off, which the owner
// kept hitting, so the glide is animated by hand and cannot degrade.
// The jump is instant only when the OWNER set the motion level to
// `off` in the admin settings.
import { ownerMotionLevel } from './level.js';

const SHOW_AFTER_PX = 550;

function initBackToTop() {
    try {
        document.querySelectorAll('[data-back-to-top]').forEach((btn) => {
            if (btn.dataset.backToTopInit) return;
            btn.dataset.backToTopInit = '1';

            let ticking = false;
            const sync = () => {
                ticking = false;
                btn.classList.toggle('is-visible', window.scrollY > SHOW_AFTER_PX);
            };
            const onScroll = () => {
                if (!ticking) {
                    ticking = true;
                    window.requestAnimationFrame(sync);
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            document.addEventListener('livewire:navigating', () => {
                window.removeEventListener('scroll', onScroll);
            }, { once: true });
            sync();

            btn.addEventListener('click', () => {
                if (ownerMotionLevel() === 'off') {
                    window.scrollTo(0, 0);
                    return;
                }
                const startY = window.scrollY;
                if (startY <= 0) return;
                const duration = 650;
                const startT = performance.now();
                let raf = 0;
                const cancel = () => cancelAnimationFrame(raf);
                window.addEventListener('wheel', cancel, { passive: true, once: true });
                window.addEventListener('touchstart', cancel, { passive: true, once: true });
                const step = (now) => {
                    const t = Math.min(1, (now - startT) / duration);
                    const eased = 1 - Math.pow(1 - t, 3);
                    window.scrollTo(0, Math.round(startY * (1 - eased)));
                    if (t < 1) raf = requestAnimationFrame(step);
                };
                raf = requestAnimationFrame(step);
            });
        });
    } catch (e) { /* never throw */ }
}

document.addEventListener('DOMContentLoaded', initBackToTop);
document.addEventListener('livewire:navigated', initBackToTop);
