@props(['threshold' => null])
{{-- M45: static red free-shipping strip (spec §6.2 step 10). No loop.
     Hidden when the free-shipping threshold is 0 (disabled). --}}
@php $value = $threshold ?? setting('free_shipping_threshold', 3000); @endphp
@if((float) $value > 0)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-2 rounded-lg bg-danger px-4 py-2.5 font-bold text-white']) }}>
        <x-icons.zap :size="18" aria-hidden="true" />
        <span>{{ __('store.free_shipping_strip', ['amount' => \App\Support\Money::format((string) $value)]) }}</span>
    </div>
@endif
