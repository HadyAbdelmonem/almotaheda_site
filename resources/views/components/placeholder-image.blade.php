@props(['product' => null, 'icon' => null, 'name' => '', 'ratio' => 'square'])
{{-- Generated placeholder (spec §5 placeholders): tinted background + the
     category's electric icon. Never a plain gray box, never external images. --}}
@php
    $iconKey = $icon ?? $product?->category?->effectiveIcon() ?? 'zap';
    if (! array_key_exists($iconKey, config('icons'))) {
        $iconKey = 'zap';
    }
    $tints = ['bg-amber-100', 'bg-sky-100', 'bg-emerald-100', 'bg-rose-100', 'bg-indigo-100', 'bg-orange-100'];
    $seed = $product ? (int) $product->id : crc32((string) $name);
    $tint = $tints[abs($seed) % count($tints)];
@endphp
<div {{ $attributes->merge(['class' => "flex items-center justify-center {$tint} text-ink/60 ".($ratio === 'square' ? 'aspect-square' : 'aspect-[4/3]')]) }}
     role="img" aria-label="{{ $name ?: $product?->name }}">
    <x-electric.tool-icon :name="$iconKey" animate="none" :size="72" />
</div>
