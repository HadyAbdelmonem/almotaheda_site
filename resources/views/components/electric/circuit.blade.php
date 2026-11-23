@props(['variant' => 'banner', 'pulses' => 2])
{{-- E02: decorative circuit-board panel (spec 11.6), max 3 pulsing paths. --}}
<div {{ $attributes->merge(['class' => 'ambient pointer-events-none select-none']) }} x-live aria-hidden="true" data-variant="{{ $variant }}">
    <svg class="h-full w-full" viewBox="0 0 200 80" preserveAspectRatio="none" fill="none" stroke="var(--color-wire-dark)" stroke-width="1.5">
        <path d="M200 15 H120 L100 35 H40"/>
        <path d="M200 65 H150 L130 45 H60"/>
        <path d="M200 40 H170"/>
        @for($i = 0; $i < min(3, (int) $pulses); $i++)
            <path class="wire-pulse" pathLength="600" d="M200 {{ [15, 65, 40][$i] }} H{{ [120, 150, 170][$i] }}" stroke="var(--color-spark)" stroke-width="1.5"/>
        @endfor
        <circle cx="40" cy="35" r="3" fill="var(--color-primary)" stroke="none"/>
        <circle cx="60" cy="45" r="3" fill="var(--color-primary)" stroke="none"/>
        <circle cx="170" cy="40" r="3" fill="var(--color-primary)" stroke="none"/>
    </svg>
</div>
