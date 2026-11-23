// x-reveal Alpine directive (spec 11.5): IntersectionObserver, once,
// threshold 0.15. Falls back to revealing everything when IO is missing
// or setup fails, and reveals immediately at motion level "off".
import { motionLevel } from './level.js';

function revealAll() {
    document.querySelectorAll('[data-reveal]:not(.is-visible)').forEach((el) => {
        el.classList.add('is-visible');
    });
}

document.addEventListener('alpine:init', () => {
    try {
        window.Alpine.directive('reveal', (el, { modifiers }) => {
            try {
                el.setAttribute('data-reveal', '');
                if (modifiers.includes('stagger')) {
                    Array.from(el.children).forEach((child, index) => {
                        child.setAttribute('data-reveal', '');
                        child.style.setProperty('--reveal-delay', `${Math.min(index, 7) * 60}ms`);
                    });
                }
                if (motionLevel() === 'off' || !('IntersectionObserver' in window)) {
                    el.classList.add('is-visible');
                    return;
                }
                const targets = modifiers.includes('stagger') ? [el, ...el.children] : [el];
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15 });
                targets.forEach((target) => observer.observe(target));
                document.addEventListener('livewire:navigating', () => observer.disconnect(), { once: true });
            } catch (e) {
                el.classList.add('is-visible');
            }
        });
    } catch (e) {
        revealAll();
    }
});
