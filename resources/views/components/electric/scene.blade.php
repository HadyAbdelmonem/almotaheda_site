@props(['name' => 'empty-cart'])
{{-- E16: state illustrations composed from E02-E05 (spec 11.6/6.10). Real content first; animation is a bonus. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-4 text-center']) }} data-scene="{{ $name }}">
    @switch($name)
        @case('404')
            {{-- M70: pressing the plug (a real button) plays the plug-in
                 sequence; the bulb lights via the plug-toggled event.
                 Without JS the scene is static and the page content,
                 search and links below it work normally. --}}
            <div x-data="{ lit: false }" x-on:plug-toggled.window="lit = $event.detail.connected" class="flex flex-col items-center gap-4">
                <x-electric.plug-socket state="apart" :interactive="true" />
                <x-electric.bulb :on="false" bind-on="lit" :size="56" />
            </div>
            @break
        @case('500')
            {{-- M71: the breaker is a real switch; the 500 error page
                 listens for switch:on and reloads after the flip. --}}
            <x-electric.switch :checked="false" label="{{ __('store.retry') }}" />
            @break
        @case('success')
            <x-electric.plug-socket state="connected" />
            <x-electric.bulb :on="true" :flicker="true" :size="56" />
            @break
        @case('empty-wishlist')
            <x-electric.plug-socket state="apart" />
            <x-electric.bulb :on="false" :size="56" />
            @break
        @case('empty-search')
            <x-electric.bulb :on="false" :size="56" />
            @break
        @default
            <x-electric.plug-socket state="apart" />
    @endswitch
    {{ $slot }}
</div>
