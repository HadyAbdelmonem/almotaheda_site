{{-- Header live search (spec §6.4, M16/M35). Debounce 300ms; dropdown fades
     in; keyboard: ArrowUp/ArrowDown move the active row, Enter opens it,
     Escape closes. On mobile the panel spans the full width under the header. --}}
<div class="relative w-full" x-data="{ open: false, active: -1 }"
     x-on:keydown.escape="open = false"
     x-on:click.outside="open = false">
    <label class="sr-only" for="live-search-input">{{ __('store.search') }}</label>
    <div class="relative">
        <input id="live-search-input" type="search" autocomplete="off"
               wire:model.live.debounce.300ms="query"
               x-on:focus="open = true" x-on:input="open = true; active = -1"
               x-on:keydown.arrow-down.prevent="active = Math.min(active + 1, {{ max(0, $results->count() - 1) }})"
               x-on:keydown.arrow-up.prevent="active = Math.max(active - 1, -1)"
               x-on:keydown.enter.prevent="if (active >= 0) { document.getElementById('live-search-result-' + active)?.click() } else if ($wire.query.trim().length >= 2) { window.location = '{{ url('search') }}?q=' + encodeURIComponent($wire.query) }"
               placeholder="{{ __('store.search_placeholder') }}"
               class="min-h-11 w-full rounded-lg border border-wire-dark/30 bg-surface pe-10 ps-4 font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
               role="combobox" aria-expanded="true" x-bind:aria-expanded="open.toString()" aria-controls="live-search-results">
        <span class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true">
            <span wire:loading wire:target="query"><x-electric.bolt-loader :size="18" /></span>
            <span wire:loading.remove wire:target="query">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            </span>
        </span>
    </div>

    @if(mb_strlen($query) >= 2)
        <div id="live-search-results" role="listbox" x-show="open" x-cloak
             class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-xl bg-surface shadow-md motion-any:animate-fade-in sm:min-w-[380px]">
            {{-- M35: skeleton rows while the request is in flight --}}
            <div wire:loading wire:target="query" class="space-y-2 p-3" aria-hidden="true">
                @for($i = 0; $i < 3; $i++)
                    <div class="ambient flex animate-pulse items-center gap-3" x-live>
                        <span class="h-12 w-12 rounded-lg bg-wire/60"></span>
                        <span class="h-4 flex-1 rounded bg-wire/60"></span>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove wire:target="query">
                @forelse($results as $i => $result)
                    @php $card = \App\Support\ProductDisplay::cardPricing($result); @endphp
                    <a id="live-search-result-{{ $i }}" href="{{ route('products.show', $result->slug) }}" wire:navigate
                       role="option" x-bind:aria-selected="(active === {{ $i }}).toString()"
                       class="flex min-h-11 items-center gap-3 px-3 py-2 hover:bg-canvas focus:bg-canvas focus:outline-none"
                       x-bind:class="active === {{ $i }} ? 'bg-canvas' : ''"
                       x-on:mouseenter="active = {{ $i }}">
                        @php $thumb = \App\Support\ProductDisplay::primaryImageUrl($result, 'thumb'); @endphp
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="" width="48" height="48" loading="lazy" class="h-12 w-12 rounded-lg bg-white object-contain">
                        @else
                            <x-placeholder-image :product="$result" class="h-12 w-12 shrink-0 rounded-lg [&_svg]:h-6 [&_svg]:w-6" />
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-ink">{{ $result->name }}</span>
                            @if($result->model_code)
                                <span class="block text-xs tabular-nums text-muted" dir="ltr">{{ $result->model_code }}</span>
                            @endif
                        </span>
                        <span class="shrink-0 font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($card['pricing']['unit_price']) }}</span>
                    </a>
                @empty
                    <p class="px-4 py-3 text-sm text-muted">{{ __('store.search_no_live_results') }}</p>
                @endforelse

                @if($results->isNotEmpty())
                    <a href="{{ url('search') }}?q={{ urlencode($query) }}" wire:navigate
                       class="block bg-canvas px-4 py-3 text-center font-bold text-link hover:underline">
                        {{ __('store.view_all_results') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
