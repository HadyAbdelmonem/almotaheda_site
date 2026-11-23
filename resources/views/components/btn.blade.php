@props(['variant' => 'primary', 'loading' => false, 'href' => null, 'type' => 'button'])
{{-- E13: button variants (spec 11.6/10.5). Spark fires only after server-confirmed success (fx.js callers). --}}
@php
    $classes = [
        'primary' => 'btn-sheen bg-primary text-ink hover:bg-primary-600',
        'secondary' => 'bg-ink text-white',
        'outline' => 'border border-ink/30 bg-transparent text-ink',
        'whatsapp' => 'bg-success text-white',
    ][$variant] ?? 'bg-primary text-ink';
    $base = 'relative inline-flex min-h-11 items-center justify-center gap-2 overflow-hidden rounded-lg px-5 font-bold motion-any:transition-transform motion-any:active:scale-[.97] disabled:opacity-60 '.$classes;
@endphp
@if($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $base]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($loading) {{ $attributes->merge(['class' => $base]) }}>
        @if($loading)<x-electric.bolt-loader :size="18" />@endif
        {{ $slot }}
    </button>
@endif
