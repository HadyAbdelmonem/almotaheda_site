@php $waNumber = preg_replace('/\D+/', '', (string) setting('whatsapp_number', '')); @endphp
{{-- Back-to-top float (owner request, 2026-11-23): arrow-only circular
     button stacked directly above the WhatsApp float (same column, same
     size); when no WhatsApp number is configured it takes the float's
     base position instead. Hidden until the page is scrolled ~550px —
     motion/back-to-top.js toggles .is-visible and handles the click
     (smooth scroll, instant at motion off / reduced motion). --}}
<button type="button" data-back-to-top aria-label="{{ __('store.back_to_top') }}"
        class="fixed start-4 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-ink text-white shadow-md motion-any:active:scale-95 {{ $waNumber !== '' ? 'bottom-[9.25rem] md:bottom-[5.75rem]' : 'bottom-20 md:bottom-6' }}">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-primary"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
</button>
