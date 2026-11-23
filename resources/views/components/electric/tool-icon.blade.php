@props(['name' => 'zap', 'animate' => 'hover', 'size' => 40])
{{-- E12: animated category/tool icon (spec 11.6/11.7). Unknown keys fall back to zap. --}}
@php $iconName = array_key_exists($name, config('icons', [])) ? $name : 'zap'; @endphp
<span {{ $attributes->merge(['class' => 'tool-icon inline-block']) }} data-icon="{{ $iconName }}" data-animate="{{ $animate }}" aria-hidden="true">
    <x-dynamic-component :component="'icons.'.$iconName" :size="$size" />
</span>
