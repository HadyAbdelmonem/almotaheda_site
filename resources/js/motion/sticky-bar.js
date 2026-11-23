// Mobile sticky buy bar (spec 11.5): slides in when the main buy button
// leaves the viewport. Elements: [data-sticky-bar] with
// data-sticky-target="<selector of the main buy button>".
function initStickyBars() {
    try {
        document.querySelectorAll('[data-sticky-bar]').forEach((bar) => {
            if (bar.dataset.stickyInit) return;
            bar.dataset.stickyInit = '1';
            const selector = bar.getAttribute('data-sticky-target');
            const anchor = selector ? document.querySelector(selector) : null;
            if (!anchor || !('IntersectionObserver' in window)) return;
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    bar.classList.toggle('sticky-bar-visible', !entry.isIntersecting);
                });
            }, { threshold: 0 });
            observer.observe(anchor);
            document.addEventListener('livewire:navigating', () => observer.disconnect(), { once: true });
        });
    } catch (e) { /* never throw */ }
}

document.addEventListener('DOMContentLoaded', initStickyBars);
document.addEventListener('livewire:navigated', initStickyBars);
