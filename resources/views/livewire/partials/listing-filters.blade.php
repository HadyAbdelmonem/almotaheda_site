{{-- Filter panel shared by the desktop sidebar and the mobile sheet.
     All controls are Livewire-bound; state is mirrored to the URL by the
     component's #[Url] properties. $prefix keeps element ids unique. --}}
@props(['prefix' => 'f'])

<div class="space-y-6">
    @if($mode === 'category' && $category && $category->children->where('is_active', true)->isNotEmpty())
        <fieldset>
            <legend class="mb-2 font-bold text-ink">{{ __('store.all_categories') }}</legend>
            <div class="flex flex-wrap gap-2" data-auto-animate>
                <button type="button" wire:click="$set('childId', null)"
                        class="min-h-11 rounded-full border px-4 font-semibold {{ $childId === null ? 'border-primary bg-primary/15' : 'border-wire-dark/30' }}">
                    {{ $category->name }}
                </button>
                @foreach($category->children->where('is_active', true) as $child)
                    <button type="button" wire:click="$set('childId', {{ $child->id }})"
                            class="min-h-11 rounded-full border px-4 font-semibold {{ $childId === $child->id ? 'border-primary bg-primary/15' : 'border-wire-dark/30' }}">
                        {{ $child->name }}
                    </button>
                @endforeach
            </div>
        </fieldset>
    @endif

    @if($availableBrands->isNotEmpty() && $mode !== 'brand')
        <fieldset>
            <legend class="mb-2 font-bold text-ink">{{ __('store.brands') }}</legend>
            <div class="space-y-1">
                @foreach($availableBrands as $availableBrand)
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 font-semibold">
                        <input type="checkbox" value="{{ $availableBrand->id }}" wire:model.live="brandIds" class="h-5 w-5 accent-[#E0A800]">
                        {{ $availableBrand->name }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endif

    <fieldset>
        <legend class="mb-2 font-bold text-ink">{{ __('store.price_range') }}</legend>
        <div class="flex items-center gap-2">
            <label class="sr-only" for="{{ $prefix }}-min">{{ __('store.price_from') }}</label>
            <input id="{{ $prefix }}-min" type="number" min="0" wire:model.live.debounce.500ms="priceMin"
                   placeholder="{{ __('store.price_from') }}" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 tabular-nums">
            <span aria-hidden="true">—</span>
            <label class="sr-only" for="{{ $prefix }}-max">{{ __('store.price_to') }}</label>
            <input id="{{ $prefix }}-max" type="number" min="0" wire:model.live.debounce.500ms="priceMax"
                   placeholder="{{ __('store.price_to') }}" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 tabular-nums">
        </div>
        @if($priceBounds['min'] !== null)
            <p class="mt-1 text-xs text-muted tabular-nums">{{ \App\Support\Money::format((string) $priceBounds['min']) }} — {{ \App\Support\Money::format((string) $priceBounds['max']) }}</p>
        @endif
    </fieldset>

    <div>
        {{-- In-stock only: E05 breaker switch bound to the Livewire property. --}}
        <div class="flex min-h-11 items-center gap-2">
            <button type="button" role="switch" aria-checked="{{ $inStock ? 'true' : 'false' }}"
                    wire:click="$toggle('inStock')" aria-label="{{ __('store.in_stock_only') }}"
                    class="inline-flex min-h-11 items-center">
                <span class="relative inline-block h-[30px] w-[52px] shrink-0 rounded-full border border-wire-dark/30 {{ $inStock ? 'bg-primary' : 'bg-wire' }}">
                    <span class="switch-knob absolute top-[3px] start-[3px] block h-6 w-6 rounded-md border border-wire-dark/20 bg-surface shadow-sm" style="--knob-travel: -22px"></span>
                </span>
            </button>
            <span class="font-bold text-ink">{{ __('store.in_stock_only') }}</span>
        </div>
    </div>

    @foreach($attrOptions as $key => $option)
        <fieldset>
            <legend class="mb-2 font-bold text-ink">
                {{ $option['attribute']->name_ar }}@if($option['attribute']->unit_label) <span class="text-sm text-muted">({{ $option['attribute']->unit_label }})</span>@endif
            </legend>
            <div class="flex flex-wrap gap-2" data-auto-animate>
                <button type="button" wire:click="setAttr('{{ $key }}', '')"
                        class="min-h-11 rounded-full border px-4 font-semibold {{ !isset($attr[$key]) ? 'border-primary bg-primary/15' : 'border-wire-dark/30' }}">
                    {{ __('store.view_all') }}
                </button>
                @foreach($option['values'] as $value)
                    <button type="button" wire:click="setAttr('{{ $key }}', '{{ $value }}')"
                            aria-pressed="{{ ($attr[$key] ?? null) === $value ? 'true' : 'false' }}"
                            class="min-h-11 rounded-full border px-4 font-semibold {{ ($attr[$key] ?? null) === $value ? 'border-primary bg-primary/15' : 'border-wire-dark/30' }}">
                        {{ $option['labels'][$value] }}
                    </button>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    @if($hasActiveFilters)
        <x-btn variant="outline" class="w-full" wire:click="clearFilters">{{ __('store.clear_filters') }}</x-btn>
    @endif
</div>
