// Back-to-top float (owner request, 2026-11-23): the button is rendered
// by components/back-to-top.blade.php, stacked above the WhatsApp float.
// It stays hidden until the page is scrolled past ~550px, then fades and
// slides in (CSS in app.css, [data-back-to-top]). Click scrolls to the
// top smoothly; at motion level `off` (which includes the browser's
// prefers-reduced-motion downgrade) the jump is instant.
import { motionLevel } from './level.js';

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
                const instant = motionLevel() === 'off';
                window.scrollTo({ top: 0, behavior: instant ? 'auto' : 'smooth' });
            });
        });
    } catch (e) { /* never throw */ }
}

document.addEventListener('DOMContentLoaded', initBackToTop);
document.addEventListener('livewire:navigated', initBackToTop);
