@props(['product'])
{{-- Product card (spec §10.5, M30): square image, sale badge top-start,
     brand, 2-line name, model code (ltr), price row, stock badge and a
     full-width add-to-cart button. Whole card is a link except buttons.
     Add-to-cart is wired to the real cart (Phase 5A); variant products
     link to the product page to pick a variant first. --}}
@php
    $card = \App\Support\ProductDisplay::cardPricing($product);
    $pricing = $card['pricing'];
    $state = \App\Support\ProductDisplay::stockState($product, $product->has_variants ? null : null);
    $imageUrl = \App\Support\ProductDisplay::primaryImageUrl($product, 'thumb');
    $url = route('products.show', $product->slug);
@endphp
<article {{ $attributes->merge(['class' => 'product-card group relative flex flex-col overflow-hidden rounded-xl bg-surface shadow-sm motion-any:transition-transform motion-any:duration-200 hover:-translate-y-1']) }} wire:key="product-card-{{ $product->id }}">
    {{-- Wishlist heart (M80) at the top-end, outside the card link. --}}
    <span class="absolute end-2 top-2 z-10">
        <livewire:wishlist-button :product-id="$product->id" :key="'wishlist-'.$product->id" />
    </span>
    <a href="{{ $url }}" wire:navigate class="block" aria-label="{{ $product->name }}">
        <div class="relative" data-fly-source>
            @if($imageUrl)
                @php $cardMedia = $product->getFirstMedia('gallery'); @endphp
                <img src="{{ $imageUrl }}" alt="{{ $product->name }}" width="400" height="400" loading="lazy"
                     @if($cardMedia) srcset="{{ $cardMedia->getUrl('thumb') }} 400w, {{ $cardMedia->getUrl('medium') }} 800w, {{ $cardMedia->getUrl('large') }} 1200w" sizes="(max-width: 767px) 50vw, (max-width: 1279px) 33vw, 240px" @endif
                     class="aspect-square w-full bg-white object-contain p-3 motion-any:transition-transform motion-any:duration-200 group-hover:scale-[1.04]">
            @else
                <x-placeholder-image :product="$product" class="w-full" />
            @endif
            @if($pricing['discount_percent'])
                <span class="absolute start-2 top-2"><x-sale-badge :percent="$pricing['discount_percent']" /></span>
            @endif
        </div>
        <div class="flex flex-1 flex-col gap-1 px-3 pt-3">
            @if($product->brand)
                <span class="text-xs font-semibold text-muted">{{ $product->brand->name }}</span>
            @endif
            <h3 class="line-clamp-2 min-h-[2.6em] text-[15px] font-semibold leading-snug text-ink">{{ $product->name }}</h3>
            @if($product->model_code)
                <span class="text-xs tabular-nums text-muted" dir="ltr">{{ $product->model_code }}</span>
            @endif
            <x-price :pricing="$pricing" :product="$product" :starts-from="$card['starts_from']" />
            <x-stock-badge :state="$state" />
        </div>
    </a>
    <div class="mt-auto p-3 pt-2">
        @if($state === 'out')
            <x-btn variant="outline" class="w-full" disabled>{{ __('store.out_of_stock') }}</x-btn>
        @elseif($product->has_variants)
            <x-btn :href="$url" class="w-full">{{ __('store.choose_variant') }}</x-btn>
        @else
            <livewire:add-to-cart-button :product-id="$product->id" :key="'add-to-cart-'.$product->id" />
        @endif
    </div>
</article>
