{{-- Cart page (spec §6.6, M50–M53). Prices are recomputed server-side
     on every render; warnings flag lines whose price/stock changed. --}}
<div class="mx-auto max-w-7xl px-4 py-10 md:px-6">
    <h1 class="text-2xl font-extrabold text-ink md:text-3xl">{{ __('store.cart') }}</h1>

    @if($lines === [])
        {{-- M53: empty-cart scene (E16). --}}
        <div class="mt-10 rounded-xl bg-surface px-4 py-12 shadow-sm">
            <x-electric.scene name="empty-cart">
                <p class="text-lg font-bold text-ink">{{ __('store.empty_cart') }}</p>
                <x-btn :href="url('/')" class="mt-2">{{ __('store.browse_products') }}</x-btn>
            </x-electric.scene>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
            <div>
                @if($lineError)
                    <p class="mb-3 rounded-lg bg-danger/10 px-3 py-2 font-semibold text-danger" role="alert">{{ $lineError }}</p>
                @endif
                {{-- M51: line removal fades/translates via auto-animate on a
                     stable wire:key list; totals flash via an opacity overlay. --}}
                <ul class="space-y-3" data-auto-animate>
                    @foreach($lines as $line)
                        @php
                            $product = $line['product'];
                            $imageUrl = $product ? \App\Support\ProductDisplay::primaryImageUrl($product, 'thumb') : null;
                            $lineKey = 'cart-line-'.$line['product_id'].'-'.($line['variant_id'] ?? 'base');
                        @endphp
                        <li wire:key="{{ $lineKey }}" class="rounded-xl bg-surface p-4 shadow-sm">
                            <div class="flex gap-3">
                                @if($product && $imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}" width="96" height="96" loading="lazy" class="h-24 w-24 shrink-0 rounded-lg bg-white object-contain">
                                @elseif($product)
                                    <x-placeholder-image :product="$product" class="h-24 w-24 shrink-0 rounded-lg [&_svg]:h-10 [&_svg]:w-10" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="font-bold leading-snug text-ink">
                                            @if($product)
                                                <a href="{{ route('products.show', $product->slug) }}" wire:navigate class="hover:text-link">{{ $product->name }}</a>
                                            @else
                                                {{ __('store.cart_warning_unavailable') }}
                                            @endif
                                        </h3>
                                        <button type="button" wire:click="removeLine({{ $line['product_id'] }}, {{ $line['variant_id'] ?? 'null' }})"
                                                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-danger" aria-label="{{ __('store.remove_line') }}">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0-1 13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1L6 7"/></svg>
                                        </button>
                                    </div>
                                    @if($line['variant'])
                                        <p class="text-sm text-muted">{{ $line['variant']->name }}</p>
                                    @endif
                                    @foreach($line['warnings'] as $warning)
                                        <p class="mt-1 rounded-md bg-danger/10 px-2 py-1 text-sm font-semibold text-danger" role="alert">{{ __('store.'.$warning) }}</p>
                                    @endforeach
                                    @if($line['purchasable'])
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <div class="inline-flex items-center rounded-lg border border-wire-dark/30" role="group" aria-label="{{ __('store.quantity') }}">
                                                <button type="button" wire:click="decrement({{ $line['product_id'] }}, {{ $line['variant_id'] ?? 'null' }})" class="flex min-h-11 min-w-11 items-center justify-center text-xl font-bold" aria-label="−">−</button>
                                                <span class="min-w-14 text-center font-bold tabular-nums">{{ \App\Support\ProductDisplay::formatQuantity($line['effective_quantity']) }}</span>
                                                <button type="button" wire:click="increment({{ $line['product_id'] }}, {{ $line['variant_id'] ?? 'null' }})" class="flex min-h-11 min-w-11 items-center justify-center text-xl font-bold" aria-label="+">+</button>
                                            </div>
                                            <span class="text-sm text-muted">{{ unit_label($product->unit_code) }} · {{ \App\Support\Money::format($line['unit_price']) }} {{ \App\Support\ProductDisplay::perUnitLabel($product) }}</span>
                                            <span class="ms-auto font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($line['line_total']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Summary + coupon (M52) + free-shipping cable bar (M50/E11). --}}
            <aside class="h-fit rounded-xl bg-surface p-5 shadow-sm lg:sticky lg:top-24">
                <div wire:key="cart-summary-{{ $total }}" class="totals-flash relative">
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-muted">{{ __('store.subtotal') }}</dt><dd class="font-bold tabular-nums">{{ \App\Support\Money::format($subtotal) }}</dd></div>
                        @if($coupon)
                            <div class="flex items-center justify-between" @if($couponJustApplied) x-data x-init="window.fx && window.fx.spark($el)" @endif>
                                <dt class="text-muted">{{ __('store.coupon_code') }} <span dir="ltr" class="font-bold text-ink">{{ $coupon->code }}</span></dt>
                                <dd class="font-bold tabular-nums text-success">− {{ \App\Support\Money::format($discount) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">{{ __('store.shipping') }}</dt><dd class="font-semibold text-muted">{{ __('store.shipping_at_checkout') }}</dd></div>
                        <div class="flex justify-between border-t border-wire pt-3 text-lg"><dt class="font-extrabold text-ink">{{ __('store.total') }}</dt><dd class="font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($total) }}</dd></div>
                    </dl>
                </div>

                <div class="mt-5">
                    <x-free-shipping-bar :progress="$progress" :remaining="$remainingFormatted" :threshold="$threshold" />
                </div>

                @if($coupon)
                    <div class="mt-5 flex items-center justify-between rounded-lg bg-success/10 px-3 py-2">
                        <span class="font-semibold text-success">{{ __('store.coupon_applied') }}</span>
                        <button type="button" wire:click="removeCoupon" class="min-h-11 px-2 font-bold text-danger">{{ __('store.remove_coupon') }}</button>
                    </div>
                @else
                    <form wire:submit="applyCoupon" class="mt-5" novalidate>
                        <label for="coupon-code" class="mb-1 block font-semibold text-ink">{{ __('store.coupon_code') }}</label>
                        <div class="flex gap-2 {{ $couponError ? 'coupon-shake' : '' }}">
                            <input id="coupon-code" type="text" wire:model="couponInput" dir="ltr"
                                   class="min-h-11 min-w-0 flex-1 rounded-lg border border-wire-dark/30 px-3 text-left focus:outline-none focus:ring-2 focus:ring-primary">
                            <x-btn type="submit" wire:loading.attr="disabled">{{ __('store.apply') }}</x-btn>
                        </div>
                        @if($couponError)
                            <p class="mt-2 flex items-center gap-1 text-sm font-semibold text-danger" role="alert">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                                {{ $couponError }}
                            </p>
                        @endif
                    </form>
                @endif

                <x-btn :href="url('/checkout')" class="mt-6 w-full">{{ __('store.checkout') }}</x-btn>
                <p class="mt-3 text-center text-sm text-muted">{{ setting('delivery_estimate_text', '') }}</p>
            </aside>
        </div>
    @endif
</div>
