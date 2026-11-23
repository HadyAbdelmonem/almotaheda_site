@props(['count' => 0, 'href' => '/cart'])
{{-- E14: header cart icon = socket + count badge (spec 11.6). Feedback only after server confirms an add. --}}
<a href="{{ $href }}" wire:navigate data-cart-target {{ $attributes->merge(['class' => 'relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-ink']) }}
   aria-label="{{ __('store.cart') }}">
    <x-icons.plug-socket :size="26" />
    <span class="cart-badge absolute -top-0.5 -end-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-extrabold text-ink"
        aria-live="polite">{{ (int) $count }}</span>
</a>
