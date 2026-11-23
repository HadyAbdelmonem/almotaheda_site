// Loading splash hide logic (OWNER OVERRIDE of the spec §11.2 preloader ban,
// owner request 2026-11-23; see components/splash.blade.php).
// The splash is an overlay only — the page renders normally beneath it.
// Hide rules:
//  - motion off / reduced motion: remove immediately on DOMContentLoaded.
//  - lite / full: fade out as soon as the window `load` event fires, with a
//    ~300ms minimum display to avoid a flicker, then remove the node.
//  - hard safety cap: remove at 2.5s no matter what (CSS also fades it away
//    at 2.6s, and without JS it is never displayed — see app.css).
// Soft navigations (wire:navigate) swap the body without re-running this
// module; the layout markup would be re-inserted by the swap, so any splash
// node is stripped synchronously in the navigated hook, before paint.
import { motionLevel } from './level.js';

const MIN_DISPLAY_MS = 300;
const HARD_CAP_MS = 2500;
const startedAt = window.performance ? performance.now() : 0;
let finished = false;
let initialSettled = false;

function removeSplash(el) {
    if (el && el.parentNode) {
        el.parentNode.removeChild(el);
    }
}

function hideSplash(instant) {
    if (finished) {
        return;
    }
    finished = true;
    initialSettled = true;
    const el = document.querySelector('[data-splash]');
    if (!el) {
        return;
    }
    try {
        if (instant || motionLevel() === 'off') {
            removeSplash(el);
            return;
        }
        el.classList.add('is-hidden');
        window.setTimeout(() => removeSplash(el), 450);
    } catch (e) { /* never throw; CSS fallback still clears the overlay */ }
}

function hideWhenReady() {
    const elapsed = window.performance ? performance.now() - startedAt : MIN_DISPLAY_MS;
    const wait = Math.max(0, MIN_DISPLAY_MS - elapsed);
    window.setTimeout(() => hideSplash(false), wait);
}

try {
    // Level off: do not wait for full load — hide as early as possible.
    if (motionLevel() === 'off') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => hideSplash(true), { once: true });
        } else {
            hideSplash(true);
        }
    } else if (document.readyState === 'complete') {
        hideWhenReady();
    } else {
        window.addEventListener('load', hideWhenReady, { once: true });
    }
    // Absolute cap even if `load` hangs on a slow asset.
    window.setTimeout(() => hideSplash(true), HARD_CAP_MS);
    // Never (re)appear on SPA navigations: strip any swapped-in node at once.
    // Guarded by initialSettled so an early navigated event around the first
    // load cannot kill the splash before its own hide logic ran.
    document.addEventListener('livewire:navigated', () => {
        if (!initialSettled) {
            return;
        }
        finished = false;
        hideSplash(true);
    });
} catch (e) { /* never throw */ }
