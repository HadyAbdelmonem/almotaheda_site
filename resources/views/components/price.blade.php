@props(['pricing', 'product', 'startsFrom' => false])
{{-- Price block (spec §4.2, §10.5): current unit price, struck regular price
     and the per-unit Arabic label. Sale badge is rendered by the caller. --}}
@php
    $per = \App\Support\ProductDisplay::perUnitLabel($product);
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-baseline gap-x-2']) }}>
    @if($startsFrom)
        <span class="text-sm font-semibold text-muted">{{ __('store.starts_from') }}</span>
    @endif
    <span class="text-xl font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($pricing['unit_price']) }}</span>
    @if($pricing['sale_applied'] || ($pricing['tier_discount_percent'] ?? null))
        <span class="text-sm tabular-nums text-muted line-through">{{ \App\Support\Money::format($pricing['regular']) }}</span>
    @endif
    @if($per)
        <span class="text-sm font-semibold text-muted">{{ $per }}</span>
    @endif
</span>
