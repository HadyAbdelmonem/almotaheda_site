@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-10">
        {{-- M63 success scene: plays once, after the order transaction
             has committed (this page is unreachable before that). --}}
        <div class="rounded-xl bg-surface px-5 py-8 text-center shadow-sm">
            <x-electric.scene name="success">
                <h1 class="text-2xl font-extrabold text-ink">{{ __('store.order_success_title') }}</h1>
                <p class="text-muted">{{ __('store.order_success_hint') }}</p>
            </x-electric.scene>

            <div class="mt-6 flex items-center justify-center gap-2" x-data="{ copied: false }">
                <span class="text-muted">{{ __('store.order_number') }}:</span>
                <span class="text-xl font-extrabold tabular-nums text-ink" dir="ltr">{{ $order->order_number }}</span>
                <button type="button"
                        x-on:click="navigator.clipboard && navigator.clipboard.writeText('{{ $order->order_number }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex min-h-11 items-center rounded-lg border border-wire-dark/30 px-3 text-sm font-bold text-ink">
                    <span x-show="!copied">{{ __('store.copy_order_number') }}</span>
                    <span x-show="copied" x-cloak class="text-success">{{ __('store.copied') }}</span>
                </button>
            </div>

            <dl class="mx-auto mt-6 max-w-md space-y-2 text-start">
                <div class="flex justify-between"><dt class="text-muted">{{ __('store.subtotal') }}</dt><dd class="font-bold tabular-nums">{{ \App\Support\Money::format((string) $order->subtotal) }}</dd></div>
                @if((string) $order->discount_total !== '0.00')
                    <div class="flex justify-between"><dt class="text-muted">{{ __('store.coupon_code') }}</dt><dd class="font-bold tabular-nums text-success">− {{ \App\Support\Money::format((string) $order->discount_total) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-muted">{{ __('store.shipping') }}</dt><dd class="font-bold tabular-nums">{{ (string) $order->shipping_fee === '0.00' ? __('store.free') : \App\Support\Money::format((string) $order->shipping_fee) }}</dd></div>
                <div class="flex justify-between border-t border-wire pt-2 text-lg"><dt class="font-extrabold text-ink">{{ __('store.total') }}</dt><dd class="font-extrabold tabular-nums text-ink">{{ \App\Support\Money::format((string) $order->total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('store.payment_methods') }}</dt><dd class="font-semibold">{{ $order->payment_method->label() }}</dd></div>
            </dl>

            <ul class="mx-auto mt-4 max-w-md space-y-1 text-start text-sm">
                @foreach($order->items as $item)
                    <li class="flex justify-between gap-3">
                        <span class="truncate">{{ $item->name }}@if($item->variant_name) — {{ $item->variant_name }}@endif <span class="text-muted">× {{ \App\Support\ProductDisplay::formatQuantity((string) $item->quantity) }} {{ $item->unit_label_ar }}</span></span>
                        <span class="shrink-0 font-semibold tabular-nums">{{ \App\Support\Money::format((string) $item->line_total) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-btn :href="url('/')">{{ __('store.continue_shopping') }}</x-btn>
                <x-whatsapp-button :message="__('store.whatsapp_order_followup', ['order' => $order->order_number])" :label="__('store.order_via_whatsapp')" />
            </div>
        </div>
    </section>
@endsection
