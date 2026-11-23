@props(['message', 'label' => null, 'variant' => 'whatsapp'])
{{-- WhatsApp CTA (spec §8.3): always https://wa.me/{number}?text={urlencoded}.
     Hidden entirely when the store has no WhatsApp number configured. --}}
@php $number = preg_replace('/\D+/', '', (string) setting('whatsapp_number', '')); @endphp
@if($number !== '')
    <a href="https://wa.me/{{ $number }}?text={{ urlencode($message) }}" target="_blank" rel="noopener"
       {{ $attributes->merge(['class' => 'relative inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-5 font-bold motion-any:transition-transform motion-any:active:scale-[.97] '.($variant === 'whatsapp' ? 'bg-success text-white' : 'border border-success bg-transparent text-success')]) }}>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.2 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.4-.7-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.4 1.8 2.2 1.2 1.1 2.3 1.5 2.6 1.6.3.1.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l2.1 1c.3.2.5.3.6.4 0 .1 0 .7-.2 1.2z"/></svg>
        {{ $label ?? __('store.order_via_whatsapp') }}
    </a>
@endif
