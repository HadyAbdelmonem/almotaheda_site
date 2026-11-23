@extends('layouts.app')

@section('content')
    {{-- Display-only payment return (spec §4.8): this page READS the
         order's payment state; it never changes it. Only the verified
         server callback can mark a card payment paid. --}}
    <section class="mx-auto max-w-3xl px-4 py-10">
        <div class="rounded-xl bg-surface px-5 py-10 text-center shadow-sm">
            <h1 class="text-2xl font-extrabold text-ink">{{ __('store.card_payment_title') }}</h1>
            <p class="mt-3 text-muted">
                {{ __('store.order_number') }}: <span dir="ltr" class="font-bold tabular-nums">{{ $order->order_number }}</span>
            </p>
            @if($order->payment_status->value === 'paid')
                <p class="mx-auto mt-5 max-w-md rounded-lg bg-success/10 px-4 py-3 font-bold text-success">{{ __('store.card_payment_paid') }}</p>
            @else
                <p class="mx-auto mt-5 max-w-md rounded-lg bg-canvas px-4 py-3 font-semibold text-ink">{{ __('store.card_payment_pending') }}</p>
            @endif
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-btn :href="route('order.success', $order->order_number)">{{ __('store.order_success_title') }}</x-btn>
                <x-btn variant="outline" :href="url('/')">{{ __('store.back_to_home') }}</x-btn>
            </div>
        </div>
    </section>
@endsection
