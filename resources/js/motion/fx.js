// fx.spark (spec 11.5, E07): 6-particle burst inside #fx-root.
// Full motion only; one burst at a time; particles remove themselves.
import { motionLevel } from './level.js';

let bursting = false;

function fxRoot() {
    let root = document.getElementById('fx-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'fx-root';
        root.setAttribute('aria-hidden', 'true');
        root.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:60;overflow:hidden';
        document.body.appendChild(root);
    }
    return root;
}

export function spark(target) {
    try {
        if (!target || motionLevel() !== 'full' || bursting) return;
        const rect = target.getBoundingClientRect();
        if (!rect.width && !rect.height) return;
        bursting = true;
        const root = fxRoot();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        let remaining = 6;
        for (let i = 0; i < 6; i++) {
            const dot = document.createElement('span');
            dot.className = 'spark-dot';
            const angle = (Math.PI * 2 * i) / 6 + Math.random() * 0.6;
            const distance = 18 + Math.random() * 16;
            dot.style.left = `${cx}px`;
            dot.style.top = `${cy}px`;
            dot.style.setProperty('--dx', `${Math.cos(angle) * distance}px`);
            dot.style.setProperty('--dy', `${Math.sin(angle) * distance}px`);
            dot.addEventListener('animationend', () => {
                dot.remove();
                if (--remaining <= 0) bursting = false;
            }, { once: true });
            root.appendChild(dot);
        }
        setTimeout(() => { bursting = false; }, 700);
    } catch (e) {
        bursting = false;
    }
}

window.fx = { spark };
