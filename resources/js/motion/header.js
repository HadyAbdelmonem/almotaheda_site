// Header scroll state (spec 11.5): after 8px of scroll a pre-drawn
// border/overlay fades in via opacity only. No height change, no
// box-shadow animation. Registered once; survives wire:navigate.
let registered = false;

function onScroll() {
    try {
        document.querySelectorAll('[data-header]').forEach((header) => {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        });
    } catch (e) { /* never throw */ }
}

if (!registered) {
    registered = true;
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('livewire:navigated', onScroll);
    onScroll();
}
