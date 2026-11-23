@props(['state' => 'apart', 'interactive' => false])
{{-- E04: plug and socket (spec 11.6). Interactive renders the plug as a real button. --}}
<span {{ $attributes->merge(['class' => 'plug-socket inline-flex items-center']) }} data-state="{{ $state }}" x-data="{ state: @js($state) }" :data-state="state" aria-hidden="{{ $interactive ? 'false' : 'true' }}">
    <svg width="72" height="40" viewBox="0 0 72 40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="46" y="6" width="22" height="28" rx="4"/>
        <circle cx="57" cy="15" r="1.4" fill="currentColor"/>
        <circle cx="57" cy="25" r="1.4" fill="currentColor"/>
        <circle class="socket-led" cx="52" cy="10" r="1.6" fill="var(--color-success)" stroke="none"/>
        <g class="plug">
            <path d="M38 14v-5M38 26v-5" transform="translate(-8 4)"/>
            <path d="M22 12h12v4.5A7.5 7.5 0 0 1 26.5 24H22z" transform="translate(4 2)"/>
            <path d="M26 16H10a6 6 0 0 0 0 12h16" transform="translate(0 -2)"/>
        </g>
    </svg>
    @if($interactive)
        <button type="button" class="ms-2 min-h-11 rounded-lg bg-primary px-3 font-semibold text-ink"
            x-on:click="state = state === 'connected' ? 'apart' : 'connected'; $dispatch('plug-toggled', { connected: state === 'connected' })"
            :aria-pressed="state === 'connected'">
            <span x-text="state === 'connected' ? 'متصل' : 'وصّل الفيشة'"></span>
        </button>
    @endif
</span>
