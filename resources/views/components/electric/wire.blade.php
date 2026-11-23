@props(['orientation' => 'h', 'pulse' => false, 'dark' => false])
{{-- E01: decorative wire (spec 11.6). Pulse path is drawn right-to-left for RTL. --}}
<span {{ $attributes->merge(['class' => 'ambient block '.($orientation === 'v' ? 'h-full w-2' : 'w-full')]) }} x-live aria-hidden="true">
    @if($orientation === 'v')
        <svg class="h-full w-2" viewBox="0 0 8 100" preserveAspectRatio="none" fill="none">
            <path d="M4 0 V100" stroke="{{ $dark ? 'var(--color-wire-dark)' : 'var(--color-wire)' }}" stroke-width="2"/>
            @if($pulse)<path class="wire-pulse" pathLength="600" d="M4 0 V100" stroke="var(--color-spark)" stroke-width="2"/>@endif
        </svg>
    @else
        <svg class="h-2 w-full" viewBox="0 0 100 8" preserveAspectRatio="none" fill="none">
            <path d="M100 4 H0" stroke="{{ $dark ? 'var(--color-wire-dark)' : 'var(--color-wire)' }}" stroke-width="2"/>
            @if($pulse)<path class="wire-pulse" pathLength="600" d="M100 4 H0" stroke="var(--color-spark)" stroke-width="2"/>@endif
        </svg>
    @endif
</span>
