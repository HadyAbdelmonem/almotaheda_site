// Motion level handling (spec 11.3/11.5). The browser may only DOWNGRADE
// the owner-selected level, never upgrade it. The initial effective level
// (after the inline head script ran) is captured as the ceiling and
// re-applied after every wire:navigate, because Livewire may reset the
// <html data-motion> attribute from the fresh server response.
const ORDER = { off: 0, lite: 1, full: 2 };
let ceiling = null;

function downgradeForConditions(level) {
    try {
        const nav = window.navigator || {};
        const conn = nav.connection || {};
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return 'off';
        }
        if (level === 'full' && (conn.saveData || /2g$/.test(conn.effectiveType || '') ||
            (nav.deviceMemory && nav.deviceMemory <= 2) ||
            (nav.hardwareConcurrency && nav.hardwareConcurrency <= 2))) {
            return 'lite';
        }
    } catch (e) { /* never throw */ }
    return level;
}

export function motionLevel() {
    const current = document.documentElement.dataset.motion || 'full';
    return ORDER[current] !== undefined ? current : 'full';
}

export function applyMotionLevel() {
    try {
        const el = document.documentElement;
        el.classList.add('js');
        let level = downgradeForConditions(el.dataset.motion || 'full');
        if (ceiling === null) {
            ceiling = level;
        }
        // Never rise above the ceiling captured on first load.
        if (ORDER[level] > ORDER[ceiling]) {
            level = ceiling;
        }
        el.dataset.motion = level;
    } catch (e) { /* never throw */ }
}

applyMotionLevel();
document.addEventListener('livewire:navigated', applyMotionLevel);
