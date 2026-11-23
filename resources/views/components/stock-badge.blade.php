@props(['state' => 'in', 'text' => null])
{{-- E10: stock badge; bulb dot states (spec 11.6). Text always present. --}}
@php
    $defaults = ['in' => __('store.in_stock'), 'low' => __('store.low_stock'), 'out' => __('store.out_of_stock')];
    $dot = ['in' => 'bg-success', 'low' => 'bg-primary', 'out' => 'bg-muted/50'][$state] ?? 'bg-muted/50';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-semibold']) }} @if($state === 'low') data-reveal @endif>
    <span class="stock-dot inline-block h-2.5 w-2.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $text ?? ($defaults[$state] ?? $defaults['in']) }}
</span>
