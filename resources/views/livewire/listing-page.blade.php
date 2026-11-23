<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
    <x-breadcrumbs :items="array_filter([
        $category?->parent ? ['label' => $category->parent->name, 'url' => route('categories.show', $category->parent->slug)] : null,
        ['label' => $title],
    ])" />

    <h1 class="mt-3 text-[28px] font-extrabold leading-[1.35] text-ink">{{ $title }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('store.products_count', ['count' => $products->total()]) }}</p>

    {{-- Mobile filter/sort bar (sticky under the header, §6.3) --}}
    <div class="sticky top-16 z-30 mt-4 flex gap-2 lg:hidden" x-data>
        <button type="button" class="min-h-11 flex-1 rounded-lg bg-ink font-bold text-white"
                x-on:click="document.getElementById('filter-sheet').showModal()">
            {{ __('store.filters') }}
        </button>
        <label class="flex min-h-11 flex-1 items-center gap-2 rounded-lg bg-surface px-3 shadow-sm">
            <span class="font-bold">{{ __('store.sort') }}</span>
            <select wire:model.live="sort" class="min-h-11 w-full bg-transparent font-semibold" aria-label="{{ __('store.sort') }}">
                <option value="latest">{{ __('store.sort_latest') }}</option>
                <option value="price_asc">{{ __('store.sort_price_asc') }}</option>
                <option value="price_desc">{{ __('store.sort_price_desc') }}</option>
                <option value="best_sellers">{{ __('store.sort_best_sellers') }}</option>
            </select>
        </label>
    </div>

    <div class="mt-4 grid gap-6 lg:grid-cols-[260px_1fr]">
        {{-- Desktop sidebar --}}
        <aside class="hidden rounded-xl bg-surface p-4 shadow-sm lg:block self-start">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-extrabold text-ink">{{ __('store.filters') }}</h2>
                <label class="flex items-center gap-2 text-sm">
                    <span class="font-bold">{{ __('store.sort') }}</span>
                    <select wire:model.live="sort" class="min-h-11 rounded-lg border border-wire-dark/30 px-2 font-semibold" aria-label="{{ __('store.sort') }}">
                        <option value="latest">{{ __('store.sort_latest') }}</option>
                        <option value="price_asc">{{ __('store.sort_price_asc') }}</option>
                        <option value="price_desc">{{ __('store.sort_price_desc') }}</option>
                        <option value="best_sellers">{{ __('store.sort_best_sellers') }}</option>
                    </select>
                </label>
            </div>
            @include('livewire.partials.listing-filters', ['prefix' => 'd'])
        </aside>

        {{-- Results. M34: the grid dims and shows skeletons while loading. --}}
        <section class="relative" wire:loading.class="opacity-50" wire:target="brandIds,priceMin,priceMax,inStock,sort,attr,childId,page">
            <div wire:loading wire:target="brandIds,priceMin,priceMax,inStock,sort,attr,childId,page"
                 class="pointer-events-none absolute inset-0 z-10 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4" aria-hidden="true">
                @for($i = 0; $i < 4; $i++)
                    <div class="ambient aspect-[3/4] animate-pulse rounded-xl bg-wire/60" x-live></div>
                @endfor
            </div>

            @if($products->isEmpty())
                {{-- M72: empty-results scene (§6.10) --}}
                <div class="rounded-xl bg-surface p-8 shadow-sm">
                    <x-electric.scene name="empty-search">
                        <h2 class="text-xl font-extrabold text-ink">{{ __('store.no_results') }}</h2>
                        <p class="text-muted">{{ __('store.no_results_hint') }}</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            @if($hasActiveFilters)
                                <x-btn wire:click="clearFilters">{{ __('store.clear_filters') }}</x-btn>
                            @endif
                            <x-whatsapp-button :message="__('store.search_no_live_results').' — '.$title" />
                        </div>
                    </x-electric.scene>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4" data-auto-animate>
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <x-pagination :paginator="$products" />
            @endif
        </section>
    </div>

    {{-- Mobile filter bottom-sheet: native <dialog> gives focus containment,
         Escape-to-close and focus restoration without any package (M33). --}}
    <dialog id="filter-sheet" class="m-0 mt-auto max-h-[85vh] w-full max-w-none rounded-t-2xl p-0 shadow-md backdrop:bg-ink/50 lg:hidden"
            x-data x-on:click="if ($event.target === $el) $el.close()">
        <div class="sticky top-0 flex items-center justify-between bg-surface px-4 py-3">
            <span class="mx-auto h-1.5 w-12 rounded-full bg-wire" aria-hidden="true"></span>
            <h2 class="font-extrabold text-ink">{{ __('store.filters') }}</h2>
            <button type="button" class="min-h-11 min-w-11 font-bold" x-on:click="document.getElementById('filter-sheet').close()" aria-label="×">×</button>
        </div>
        <div class="overflow-y-auto px-4 pb-6">
            @include('livewire.partials.listing-filters', ['prefix' => 'm'])
        </div>
    </dialog>
</div>
