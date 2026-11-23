@php $waNumber = preg_replace('/\D+/', '', (string) setting('whatsapp_number', '')); @endphp
{{-- WhatsApp float (spec §8.3, M07): persisted across wire:navigate,
     hidden entirely when no number is configured, sits above sticky
     mobile bars. One subtle ring 5s after load (full motion only). --}}
@if($waNumber !== '')
    <a href="https://wa.me/{{ $waNumber }}?text={{ urlencode(__('store.whatsapp_general_message')) }}"
       target="_blank" rel="noopener" aria-label="{{ __('store.contact_whatsapp') }}"
       class="fixed bottom-20 start-4 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-success text-white shadow-md md:bottom-6 motion-any:transition-transform motion-any:active:scale-95">
        <span class="whatsapp-ring pointer-events-none absolute inset-0 rounded-full border-2 border-success motion-full:animate-ring" style="animation-delay:5s" aria-hidden="true"></span>
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.2 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.4-.7-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.4 1.8 2.2 1.2 1.1 2.3 1.5 2.6 1.6.3.1.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l2.1 1c.3.2.5.3.6.4 0 .1 0 .7-.2 1.2z"/></svg>
    </a>
@endif
