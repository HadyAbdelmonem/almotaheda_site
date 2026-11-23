{{-- One-page guest checkout (spec §6.7, M60–M62). The server
     re-validates everything inside the order transaction. --}}
<div class="mx-auto max-w-7xl px-4 py-10 md:px-6">
    <h1 class="text-2xl font-extrabold text-ink md:text-3xl">{{ __('store.checkout') }}</h1>

    <form wire:submit="submit" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]" novalidate>
        <div class="{{ $errors->any() ? 'coupon-shake' : '' }}">
            <div class="absolute -start-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            @if($orderError)
                <p class="mb-4 rounded-lg bg-danger/10 px-4 py-3 font-bold text-danger" role="alert">{{ $orderError }}</p>
            @endif

            {{-- Customer data --}}
            <section class="rounded-xl bg-surface p-5 shadow-sm" aria-label="{{ __('store.customer_data') }}">
                <h2 class="font-extrabold text-ink">{{ __('store.customer_data') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="co-name" class="mb-1 block font-semibold text-ink">{{ __('store.full_name') }}</label>
                        <input id="co-name" type="text" wire:model="name" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="co-phone" class="mb-1 block font-semibold text-ink">{{ __('store.phone') }}</label>
                        <input id="co-phone" type="tel" wire:model="phone" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="co-email" class="mb-1 block font-semibold text-ink">{{ __('store.email_optional') }}</label>
                        <input id="co-email" type="email" wire:model="email" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="co-phone-alt" class="mb-1 block font-semibold text-ink">{{ __('store.phone_alt_optional') }}</label>
                        <input id="co-phone-alt" type="tel" wire:model="phoneAlt" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('phoneAlt') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Address --}}
            <section class="mt-4 rounded-xl bg-surface p-5 shadow-sm" aria-label="{{ __('store.address_detail') }}">
                <h2 class="font-extrabold text-ink">{{ __('store.shipping_address') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="co-gov" class="mb-1 block font-semibold text-ink">{{ __('store.governorate') }}</label>
                        <select id="co-gov" wire:model.live="governorateId" class="ui-select min-h-11 w-full rounded-lg border border-wire-dark/30 bg-surface px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">{{ __('store.choose_governorate') }}</option>
                            @foreach($rates as $rate)
                                <option value="{{ $rate->id }}">{{ $rate->name_ar }} — {{ \App\Support\Money::format((string) $rate->fee) }}</option>
                            @endforeach
                        </select>
                        @error('governorateId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="co-city" class="mb-1 block font-semibold text-ink">{{ __('store.city') }}</label>
                        <input id="co-city" type="text" wire:model="city" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('city') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="co-address" class="mb-1 block font-semibold text-ink">{{ __('store.address_detail') }}</label>
                        <textarea id="co-address" wire:model="address" rows="2" class="w-full rounded-lg border border-wire-dark/30 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        @error('address') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="co-notes" class="mb-1 block font-semibold text-ink">{{ __('store.notes_optional') }}</label>
                        <textarea id="co-notes" wire:model="notes" rows="2" class="w-full rounded-lg border border-wire-dark/30 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
                @if($selectedRate && $selectedRate->est_days_min)
                    <p class="mt-3 flex items-center gap-2 text-sm text-muted">
                        <x-icons.truck :size="18" aria-hidden="true" />
                        {{ __('store.delivery_estimate') }}: {{ $selectedRate->est_days_min }}@if($selectedRate->est_days_max)–{{ $selectedRate->est_days_max }}@endif {{ __('store.days') }}
                    </p>
                @endif
            </section>

            {{-- Payment methods from settings --}}
            <section class="mt-4 rounded-xl bg-surface p-5 shadow-sm" aria-label="{{ __('store.payment_methods') }}">
                <h2 class="font-extrabold text-ink">{{ __('store.payment_methods') }}</h2>
                <div class="mt-4 space-y-3">
                    @foreach($methods as $method)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-wire-dark/30 px-4 py-3 has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                            <input type="radio" wire:model.live="paymentMethod" value="{{ $method->value }}" class="h-5 w-5 accent-[#E0A800]">
                            <span class="font-bold text-ink">{{ $method->label() }}</span>
                        </label>
                        @if($method->value === 'bank_transfer' && $paymentMethod === 'bank_transfer' && $bankDetails !== '')
                            <div class="rounded-lg bg-canvas px-4 py-3 text-sm leading-7 text-body" role="note">
                                <p class="font-bold text-ink">{{ __('store.bank_transfer_hint') }}</p>
                                <p class="whitespace-pre-line">{{ $bankDetails }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
                @error('paymentMethod') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
            </section>
        </div>

        {{-- Summary (M62: totals cross-fade when the governorate changes). --}}
        <aside class="h-fit rounded-xl bg-surface p-5 shadow-sm lg:sticky lg:top-24">
            <h2 class="font-extrabold text-ink">{{ __('store.order_summary') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach($lines as $line)
                    <li class="flex items-start justify-between gap-3 text-sm" wire:key="checkout-line-{{ $line['product_id'] }}-{{ $line['variant_id'] ?? 'base' }}">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-ink">{{ $line['product']?->name }}</span>
                            <span class="text-muted">{{ \App\Support\ProductDisplay::formatQuantity($line['effective_quantity']) }} {{ $line['product'] ? unit_label($line['product']->unit_code) : '' }} × {{ $line['unit_price'] ? \App\Support\Money::format($line['unit_price']) : '' }}</span>
                        </span>
                        <span class="shrink-0 font-bold tabular-nums">{{ $line['line_total'] ? \App\Support\Money::format($line['line_total']) : '' }}</span>
                    </li>
                @endforeach
            </ul>
            <div wire:key="checkout-totals-{{ $governorateId ?? 'none' }}-{{ $total }}" class="totals-flash relative mt-4 border-t border-wire pt-4">
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('store.subtotal') }}</dt><dd class="font-bold tabular-nums">{{ \App\Support\Money::format($subtotal) }}</dd></div>
                    @if($coupon)
                        <div class="flex justify-between"><dt class="text-muted">{{ __('store.coupon_code') }} <span dir="ltr" class="font-bold text-ink">{{ $coupon->code }}</span></dt><dd class="font-bold tabular-nums text-success">− {{ \App\Support\Money::format($discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-muted">{{ __('store.shipping') }}</dt>
                        <dd class="font-bold tabular-nums">
                            @if($fee === null)
                                <span class="font-semibold text-muted">{{ __('store.shipping_at_checkout') }}</span>
                            @elseif($fee === '0.00')
                                <span class="text-success">{{ __('store.free') }}</span>
                            @else
                                {{ \App\Support\Money::format($fee) }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-wire pt-3 text-lg"><dt class="font-extrabold text-ink">{{ __('store.total') }}</dt><dd class="font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format($total) }}</dd></div>
                </dl>
            </div>

            <div class="mt-5">
                <x-free-shipping-bar :progress="$progress" :remaining="$remainingFormatted" :threshold="$threshold" />
            </div>

            {{-- M60: confirm button with bolt-loader state. --}}
            <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                    class="btn-sheen relative mt-6 inline-flex min-h-11 w-full items-center justify-center gap-2 overflow-hidden rounded-lg bg-primary px-5 font-bold text-ink hover:bg-primary-600 disabled:opacity-60 motion-any:transition-transform motion-any:active:scale-[.97]">
                <span wire:loading.remove wire:target="submit">{{ __('store.confirm_order') }}</span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                    <x-electric.bolt-loader :size="18" /> {{ __('store.confirming_order') }}
                </span>
            </button>
            <p class="mt-3 text-center text-sm text-muted">{{ setting('delivery_estimate_text', '') }}</p>
        </aside>
    </form>
</div>
