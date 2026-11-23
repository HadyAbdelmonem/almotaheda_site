<button type="button" wire:click="toggle"
        aria-label="{{ $active ? __('store.remove_from_wishlist') : __('store.add_to_wishlist') }}"
        aria-pressed="{{ $active ? 'true' : 'false' }}"
        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full bg-white/90 shadow-sm">
    {{-- M80: heart pop 1 -> 1.3 -> 1 (finite) + fill change; static in lite/off. --}}
    <svg wire:key="heart-{{ $productId }}-{{ $active ? 'on' : 'off' }}" width="22" height="22" viewBox="0 0 24 24" fill="{{ $active ? '#DC2626' : 'none' }}" stroke="{{ $active ? '#DC2626' : '#111827' }}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" @class(['motion-any:animate-pop' => $active])>
        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
    </svg>
</button>
