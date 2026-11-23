@props(['checked' => false, 'label' => null, 'name' => null])
{{-- E05: breaker-style toggle (spec 11.6). Real switch semantics; sparks on turn-on via switch.js event. --}}
<button type="button" role="switch" aria-checked="{{ $checked ? 'true' : 'false' }}"
    @if($name) data-switch-name="{{ $name }}" @endif
    x-data="{ on: @js((bool) $checked) }" :aria-checked="on.toString()"
    x-on:click="on = !on; if (on) { $dispatch('switch:on', { target: $el }) }"
    {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center gap-2']) }}>
    <span class="relative inline-block h-[30px] w-[52px] shrink-0 rounded-full border border-wire-dark/30 transition-colors duration-200"
        :class="on ? 'bg-primary' : 'bg-wire'">
        <span class="switch-knob absolute top-[3px] start-[3px] block h-6 w-6 rounded-md bg-surface shadow-sm border border-wire-dark/20"></span>
    </span>
    @if($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
</button>
