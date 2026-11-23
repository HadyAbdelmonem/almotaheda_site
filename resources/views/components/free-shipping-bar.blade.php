@props(['progress' => 0, 'remaining' => null, 'threshold' => null])
{{-- E11: cable-style free-shipping progress (spec 11.6). Hidden when threshold is 0. --}}
@php
    $p = max(0.0, min(1.0, (float) $progress));
    $thresholdValue = $threshold ?? setting('free_shipping_threshold', 3000);
@endphp
@if((float) $thresholdValue > 0)
    <div {{ $attributes->merge(['class' => 'free-shipping']) }}>
        <div class="flex items-center gap-2">
            <x-icons.plug-zap :size="20" class="shrink-0 text-ink" />
            <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-ink" role="progressbar"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round($p * 100) }}"
                 aria-label="{{ __('store.free_shipping') }}">
                <div class="free-shipping-fill absolute inset-y-0 start-0 h-full origin-right rounded-full {{ $p >= 1 ? 'bg-success' : 'bg-primary' }}"
                     style="inline-size: 100%; transform: scaleX({{ $p }}); transition: transform 600ms var(--ease-out-soft)">
                    @if($p < 1)<span class="ambient absolute inset-0 free-shipping-sweep" x-live aria-hidden="true"></span>@endif
                </div>
            </div>
            <x-electric.bulb :on="$p >= 1" :flicker="$p >= 1" :size="26" class="shrink-0 {{ $p >= 1 ? 'text-primary-600' : 'text-muted' }}" />
        </div>
        <p class="mt-2 text-sm font-semibold text-body">
            @if($p >= 1)
                {{ __('store.free_shipping_active') }}
            @elseif($remaining !== null)
                {{ __('store.free_shipping_remaining', ['amount' => $remaining]) }}
            @endif
        </p>
    </div>
@endif
