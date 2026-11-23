@props(['on' => false, 'flicker' => false, 'size' => 48, 'bindOn' => null])
{{-- E03: bulb with data-on state and one-time flicker (spec 11.6/11.4).
     `bindOn` optionally binds data-on to a parent Alpine expression
     (used by the 404 scene: plugging in lights the bulb, M70). --}}
<span {{ $attributes->merge(['class' => 'bulb inline-block']) }} @if($bindOn) :data-on="{{ $bindOn }}" @else data-on="{{ $on ? 'true' : 'false' }}" @endif @if($flicker) data-flicker="true" @endif aria-hidden="true">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <circle class="bulb-glow" cx="24" cy="20" r="16" fill="var(--color-spark)" stroke="none"/>
        <g class="bulb-rays">
            @foreach([0,1,2,3,4,5] as $i)
                <path style="--i: {{ $i }}" d="M24 1v4" transform="rotate({{ $i * 60 }} 24 20)"/>
            @endforeach
        </g>
        <path class="bulb-glass" d="M24 8a12 12 0 0 0-7 21.6c1 1 2 2.3 2 3.9h10c0-1.6 1-2.9 2-3.9A12 12 0 0 0 24 8z"/>
        <path class="bulb-filament" d="M19.5 20c1.5 1.8 7.5 1.8 9 0"/>
        <path d="M19 38h10M20.5 42h7"/>
    </svg>
</span>
