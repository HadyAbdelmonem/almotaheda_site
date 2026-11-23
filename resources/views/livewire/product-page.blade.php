<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
    {{-- 1. Breadcrumbs --}}
    <x-breadcrumbs :items="array_filter([
        $product->category?->parent ? ['label' => $product->category->parent->name, 'url' => route('categories.show', $product->category->parent->slug)] : null,
        $product->category ? ['label' => $product->category->name, 'url' => route('categories.show', $product->category->slug)] : null,
        ['label' => $product->name],
    ])" />

    <div class="mt-4 grid gap-6 lg:grid-cols-2">
        {{-- 2. Gallery: Swiper Thumbs + Zoom inside wire:ignore (§11.9).
             Primary image is eager + fetchpriority=high and never animated in (§9 LCP). --}}
        <div wire:ignore data-gallery class="overflow-hidden rounded-xl bg-surface shadow-sm">
            @php
                $slides = collect();
                if ($variantImage) {
                    $slides->push(['url' => $variantImage, 'thumb' => $variantImage, 'alt' => $variant?->name ?? $product->name]);
                }
                foreach ($gallery as $media) {
                    $slides->push(['url' => $media->getUrl('large'), 'thumb' => $media->getUrl('thumb'), 'alt' => $media->getCustomProperty('alt') ?: $product->name, 'srcset' => $media->getUrl('medium').' 800w, '.$media->getUrl('large').' 1200w']);
                }
            @endphp
            @if($slides->isNotEmpty())
                <div class="swiper" data-gallery-main dir="rtl">
                    <div class="swiper-wrapper">
                        @foreach($slides as $i => $slide)
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="{{ $slide['url'] }}" alt="{{ $slide['alt'] }}" width="1200" height="1200"
                                         @isset($slide['srcset']) srcset="{{ $slide['srcset'] }}" sizes="(max-width: 1023px) 100vw, 600px" @endisset
                                         @if($i === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif
                                         class="aspect-square w-full bg-white object-contain p-4">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="swiper-pagination" data-gallery-pagination></div>
                </div>
                @if($slides->count() > 1)
                    <div class="swiper px-3 pb-3" data-gallery-thumbs dir="rtl">
                        <div class="swiper-wrapper">
                            @foreach($slides as $slide)
                                <div class="swiper-slide w-20 cursor-pointer">
                                    <img src="{{ $slide['thumb'] }}" alt="" width="400" height="400" loading="lazy"
                                         class="aspect-square w-full rounded-lg bg-white object-contain p-1 ring-2 ring-transparent [.swiper-slide-thumb-active_&]:ring-primary">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <x-placeholder-image :product="$product" class="w-full" />
            @endif
        </div>

        {{-- Buy box --}}
        <div class="rounded-xl bg-surface p-5 shadow-sm">
            {{-- 3. Name, model, brand --}}
            <h1 class="text-[28px] font-extrabold leading-[1.35] text-ink md:text-[36px]">{{ $product->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                @if($product->model_code)
                    <span>{{ __('store.model_code') }}: <span dir="ltr" class="font-semibold tabular-nums">{{ $product->model_code }}</span></span>
                @endif
                @if($product->brand)
                    <a href="{{ route('brands.show', $product->brand->slug) }}" wire:navigate class="font-semibold text-link hover:underline">{{ $product->brand->name }}</a>
                    @if($product->brand->is_authorized_dealer)
                        <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-bold text-success">
                            <x-icons.badge-check :size="14" />{{ __('store.authorized_dealer') }}
                        </span>
                    @endif
                @endif
            </div>

            @if($reviews->isNotEmpty())
                <div class="mt-3"><x-rating-stars :rating="$averageRating" :count="$reviews->count()" /></div>
            @endif

            {{-- 4. Price block + availability --}}
            <div class="mt-4 flex flex-wrap items-center gap-3" wire:key="price-{{ $variantId ?? 'base' }}-{{ $quantity }}">
                <span class="motion-any:transition-opacity" @if(!$startsFrom) x-data x-init="$el.classList.add('animate-fade-in')" @endif>
                    <x-price :pricing="$pricing" :product="$product" :starts-from="$startsFrom" />
                </span>
                @if($pricing['discount_percent'])
                    <x-sale-badge :percent="$pricing['discount_percent']" />
                @endif
                <x-stock-badge :state="$stockState" />
            </div>
            @if($variant)
                <p class="mt-1 text-sm text-muted">{{ __('store.sku') }}: <span dir="ltr" class="tabular-nums">{{ $variant->sku }}</span></p>
            @endif

            {{-- 5. Variant picker (M41) --}}
            @if($product->has_variants && $product->variants->isNotEmpty())
                <fieldset class="mt-5">
                    <legend class="mb-2 font-bold text-ink">{{ __('store.choose_variant') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->variants as $option)
                            <button type="button" wire:click="selectVariant({{ $option->id }})"
                                    aria-pressed="{{ $variantId === $option->id ? 'true' : 'false' }}"
                                    class="min-h-11 rounded-full border px-4 font-semibold motion-any:transition-transform motion-any:active:scale-95 {{ $variantId === $option->id ? 'border-primary bg-primary/15 text-ink' : 'border-wire-dark/30 bg-surface text-body' }}">
                                {{ $option->name }}
                            </button>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            {{-- 8+9. Quantity stepper + buy actions --}}
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <div class="inline-flex items-center rounded-lg border border-wire-dark/30" role="group" aria-label="{{ __('store.quantity') }}">
                    <button type="button" wire:click="decrement" class="flex min-h-11 min-w-11 items-center justify-center text-xl font-bold" aria-label="−">−</button>
                    <input type="text" inputmode="decimal" wire:model.lazy="quantity" aria-label="{{ __('store.quantity') }}"
                           class="min-h-11 w-16 border-x border-wire-dark/30 text-center font-bold tabular-nums">
                    <button type="button" wire:click="increment" class="flex min-h-11 min-w-11 items-center justify-center text-xl font-bold" aria-label="+">+</button>
                </div>
                <span class="text-sm font-semibold text-muted">{{ unit_label($product->unit_code) }}</span>
                <span class="text-sm text-muted">{{ __('store.total') }}: <span class="font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($lineTotal) }}</span></span>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row" data-fly-source>
                {{-- Add-to-cart (Phase 5A): server-validated via CartService;
                     cart-added/fly-to-cart fire only after success (M43). --}}
                <x-btn id="main-buy-button" class="flex-1" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart" :disabled="$stockState === 'out'">
                    {{ $stockState === 'out' ? __('store.out_of_stock') : __('store.add_to_cart') }}
                </x-btn>
                <x-whatsapp-button :message="$whatsAppMessage" :label="$stockState === 'out' ? __('store.ask_availability') : __('store.order_via_whatsapp')" class="flex-1" />
            </div>

            {{-- 10. Free-shipping red strip (M45) --}}
            <x-free-shipping-strip class="mt-4" />

            {{-- 11. Delivery estimate --}}
            <p class="mt-3 flex items-center gap-2 text-sm text-muted">
                <x-icons.truck :size="18" aria-hidden="true" />
                {{ setting('delivery_estimate_text', 'التوصيل خلال 2–5 أيام عمل') }}
            </p>
        </div>
    </div>

    {{-- 6. Technical attributes + descriptive specs --}}
    @if($technicalSpecs->isNotEmpty() || !empty($product->specs))
        <section class="mt-8 rounded-xl bg-surface p-5 shadow-sm">
            <x-section-title :title="__('store.specifications')" />
            <dl class="divide-y divide-wire">
                @foreach($technicalSpecs as $spec)
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="font-semibold text-muted">{{ $spec['label'] }}</dt>
                        <dd class="font-bold text-ink">{{ $spec['value'] }}</dd>
                    </div>
                @endforeach
                @foreach($product->specs ?? [] as $spec)
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="font-semibold text-muted">{{ $spec['label'] ?? '' }}</dt>
                        <dd class="font-bold text-ink">{{ $spec['value'] ?? '' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- 7. Quantity tiers (M42) --}}
    @if($tiers->isNotEmpty())
        <section class="mt-8 rounded-xl bg-surface p-5 shadow-sm">
            <x-section-title :title="__('store.buy_more_save')" />
            <div class="overflow-x-auto">
                <table class="w-full min-w-[420px] text-start">
                    <thead>
                        <tr class="text-sm text-muted">
                            <th class="py-2 text-start font-semibold">{{ __('store.tier_min_qty') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('store.tier_discount') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('store.tier_unit_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tiers as $tier)
                            <tr class="{{ $tier['active'] ? 'bg-primary/15 font-bold' : '' }} border-t border-wire">
                                <td class="border-s-4 py-2.5 tabular-nums {{ $tier['active'] ? 'border-primary ps-2' : 'border-transparent' }}">
                                    {{ \App\Support\ProductDisplay::formatQuantity($tier['min_qty']) }} {{ unit_label($product->unit_code) }}
                                </td>
                                <td class="py-2.5 tabular-nums">{{ rtrim(rtrim($tier['discount_percent'], '0'), '.') }}%</td>
                                <td class="py-2.5 tabular-nums">{{ \App\Support\Money::format($tier['unit_price']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- 12. Description + why-us --}}
    @if($product->description)
        <section class="mt-8 rounded-xl bg-surface p-5 shadow-sm">
            <x-section-title :title="__('store.description')" />
            <div class="leading-[1.7] text-body">{!! nl2br(e($product->description)) !!}</div>
        </section>
    @endif

    <x-trust-block class="mt-10" />

    {{-- 14. Reviews: approved only --}}
    <section class="mt-10">
        <x-section-title :title="__('store.customer_reviews')" />
        @if($reviews->isEmpty())
            <p class="rounded-xl bg-surface p-5 text-muted shadow-sm">{{ __('store.no_reviews_yet') }}</p>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($reviews as $review)
                    <article class="rounded-xl bg-surface p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-ink">{{ $review->user?->name }}</span>
                            <x-rating-stars :rating="$review->rating" />
                        </div>
                        @if($review->is_verified_purchase)
                            <span class="mt-1 inline-block rounded-full bg-success/10 px-2 py-0.5 text-xs font-bold text-success">{{ __('store.verified_purchase') }}</span>
                        @endif
                        @if($review->comment)
                            <p class="mt-2 leading-[1.7] text-body">{{ $review->comment }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
        {{-- Review submission (Phase 7): auth only, pending moderation. --}}
        <livewire:review-form :product-id="$product->id" :key="'review-form-'.$product->id" />
    </section>

    {{-- 13. Related products --}}
    @if($related->isNotEmpty())
        <section class="mt-10">
            <x-section-title :title="__('store.related_products')" />
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                @foreach($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- 15. Mobile sticky buy bar (M44) --}}
    <div data-sticky-bar data-sticky-target="#main-buy-button"
         class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-3 border-t border-wire bg-surface px-4 py-2 lg:hidden">
        <span class="font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($pricing['unit_price']) }}</span>
        <x-btn class="flex-1" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart" :disabled="$stockState === 'out'">{{ $stockState === 'out' ? __('store.out_of_stock') : __('store.add_to_cart') }}</x-btn>
    </div>
</div>
