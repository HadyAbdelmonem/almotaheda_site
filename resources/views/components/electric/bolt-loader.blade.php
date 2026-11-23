@props(['size' => 24])
{{-- E06: bolt loader (spec 11.6). Ambient only while a request is loading; static bolt when off. --}}
<span {{ $attributes->merge(['class' => 'ambient inline-flex items-center']) }} x-live role="status">
    <svg class="bolt-loader-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>
    </svg>
    <span class="sr-only">{{ __('store.loading') }}</span>
</span>
