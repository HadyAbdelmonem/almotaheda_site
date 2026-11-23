// x-live Alpine directive (spec 11.5): toggles .is-live while an ambient
// element is visible and the tab is visible. A page-wide scheduler allows
// at most 3 visible ambient loops to run at once (full motion only).
import { motionLevel } from './level.js';

const MAX_AMBIENT = 3;
const visible = new Set();

export function ambientLoopCount() {
    return document.querySelectorAll('.ambient.is-live').length;
}

function refresh() {
    try {
        let count = 0;
        const allow = motionLevel() === 'full' && !document.hidden;
        visible.forEach((el) => {
            const run = allow && count < MAX_AMBIENT;
            el.classList.toggle('is-live', run);
            if (run) count++;
        });
    } catch (e) { /* never throw */ }
}

window.motionDiagnostics = function () {
    return {
        level: motionLevel(),
        reducedMotion: !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches),
        ambientActive: ambientLoopCount(),
    };
};

document.addEventListener('alpine:init', () => {
    try {
        window.Alpine.directive('live', (el) => {
            try {
                if (!('IntersectionObserver' in window)) return;
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) visible.add(el); else visible.delete(el);
                        refresh();
                    });
                }, { rootMargin: '100px' });
                observer.observe(el);
                document.addEventListener('livewire:navigating', () => {
                    observer.disconnect();
                    visible.delete(el);
                }, { once: true });
            } catch (e) { /* never throw */ }
        });
    } catch (e) { /* never throw */ }
});

document.addEventListener('visibilitychange', refresh);
document.addEventListener('livewire:navigated', () => setTimeout(refresh, 50));
