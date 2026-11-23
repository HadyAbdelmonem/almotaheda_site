@extends('layouts.app')

@section('content')
    {{-- Hosted card payment (Phase 10). The iFrame is the provider's
         page; payment state changes ONLY via the verified server
         callback — this page and the return page never mark paid. --}}
    <section class="mx-auto max-w-3xl px-4 py-10">
        <div class="rounded-xl bg-surface px-5 py-8 shadow-sm">
            <h1 class="text-center text-2xl font-extrabold text-ink">{{ __('store.card_payment_title') }}</h1>
            <p class="mt-2 text-center text-muted">
                {{ __('store.order_number') }}: <span dir="ltr" class="font-bold tabular-nums">{{ $order->order_number }}</span>
                — {{ __('store.total') }}: {{ \App\Support\Money::format((string) $order->total) }}
            </p>
            <div class="mt-6 overflow-hidden rounded-xl border border-wire">
                <iframe src="{{ $iframeUrl }}" title="{{ __('store.card_payment_title') }}"
                        class="h-[560px] w-full" loading="eager"></iframe>
            </div>
            <p class="mt-4 text-center text-sm text-muted">{{ __('store.card_payment_hint') }}</p>
            <div class="mt-6 text-center">
                <x-btn variant="outline" :href="route('payments.paymob.return', $order->order_number)">{{ __('store.card_payment_check') }}</x-btn>
            </div>
        </div>
    </section>
@endsection
