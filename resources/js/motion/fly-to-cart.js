// Fly-to-cart (spec 11.5): listens for the browser `cart-added` event,
// which Livewire dispatches ONLY after the server confirmed the add.
// full: WAAPI arc flight of the product image clone + spark at the cart.
// lite: badge pop only. off: nothing. Never runs optimistically.
import { motionLevel } from './level.js';
import { spark } from './fx.js';

function popBadge(badge) {
    if (!badge) return;
    badge.classList.remove('animate-pop');
    void badge.offsetWidth;
    badge.classList.add('animate-pop');
}

document.addEventListener('cart-added', (event) => {
    try {
        const level = motionLevel();
        if (level === 'off') return;
        const target = document.querySelector('[data-cart-target]');
        if (!target) return;
        const badge = target.querySelector('.cart-badge');
        if (level === 'lite') {
            popBadge(badge);
            return;
        }
        const sourceEl = (event.detail && event.detail.source) ||
            (document.activeElement && document.activeElement.closest
                ? document.activeElement.closest('[data-fly-source]') : null);
        const img = sourceEl ? sourceEl.querySelector('img') : null;
        const targetRect = target.getBoundingClientRect();
        if (!img || typeof img.animate !== 'function') {
            popBadge(badge);
            spark(target);
            return;
        }
        const sourceRect = img.getBoundingClientRect();
        const clone = img.cloneNode();
        clone.style.cssText = `position:fixed;z-index:70;pointer-events:none;border-radius:12px;` +
            `left:${sourceRect.left}px;top:${sourceRect.top}px;width:${sourceRect.width}px;height:${sourceRect.height}px;object-fit:contain;background:#fff`;
        document.body.appendChild(clone);
        const dx = targetRect.left + targetRect.width / 2 - (sourceRect.left + sourceRect.width / 2);
        const dy = targetRect.top + targetRect.height / 2 - (sourceRect.top + sourceRect.height / 2);
        const animation = clone.animate([
            { transform: 'translate(0,0) scale(1)', opacity: 1 },
            { transform: `translate(${dx * 0.5}px, ${dy - 90}px) scale(.45)`, opacity: 0.9, offset: 0.55 },
            { transform: `translate(${dx}px, ${dy}px) scale(.08)`, opacity: 0.4 },
        ], { duration: 600, easing: 'cubic-bezier(0.22,1,0.36,1)' });
        const done = () => { clone.remove(); popBadge(badge); spark(target); };
        animation.onfinish = done;
        animation.oncancel = () => clone.remove();
    } catch (e) { /* never throw */ }
});
