@props(['percent'])
{{-- E09: sale badge with bolt notch and one-time flicker (spec 11.6). --}}
<span {{ $attributes->merge(['class' => 'sale-badge inline-flex items-center gap-1 rounded-full bg-danger px-2.5 py-1 text-xs font-bold text-white']) }} data-reveal>
    <x-icons.zap :size="12" />-{{ rtrim(rtrim(number_format((float) $percent, 2, '.', ''), '0'), '.') }}%
</span>
