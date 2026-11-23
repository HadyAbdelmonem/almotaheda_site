// Breaker switch side effects (spec 11.5): the switch component owns its
// own aria-checked state via Alpine and dispatches `switch:on` when it
// turns on; here we only add the confirmed-action spark (full level).
import { spark } from './fx.js';

document.addEventListener('switch:on', (event) => {
    try {
        const target = (event.detail && event.detail.target) || null;
        if (target) spark(target);
    } catch (e) { /* never throw */ }
});
