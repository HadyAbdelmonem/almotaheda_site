@props(['rating' => 0, 'count' => null])
{{-- Rating stars: static SVG stars, filled according to the average of
     approved reviews only (spec §6.2). Meaning is also in the text. --}}
@php $average = max(0, min(5, (float) $rating)); @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }} role="img"
      aria-label="{{ number_format($average, 1) }} / 5">
    <span class="inline-flex" aria-hidden="true">
        @for($i = 1; $i <= 5; $i++)
            <svg width="16" height="16" viewBox="0 0 24 24" fill="{{ $i <= round($average) ? '#FFC107' : 'none' }}"
                 stroke="#E0A800" stroke-width="1.75" stroke-linejoin="round">
                <path d="M12 3l2.7 5.6 6.1.8-4.5 4.3 1.1 6.1L12 17l-5.4 2.8 1.1-6.1L3.2 9.4l6.1-.8L12 3z"/>
            </svg>
        @endfor
    </span>
    <span class="text-sm font-semibold tabular-nums">{{ number_format($average, 1) }}</span>
    @if($count !== null)
        <span class="text-sm text-muted">({{ __('store.reviews_count', ['count' => $count]) }})</span>
    @endif
</span>
