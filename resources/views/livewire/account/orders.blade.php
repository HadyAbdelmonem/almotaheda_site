<div class="mx-auto max-w-5xl px-4 py-8">
    @include('livewire.account.partials.nav')
    @if($detail)
        <div class="mt-6 rounded-xl bg-surface p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-extrabold text-ink">{{ __('store.order_details') }} — <span dir="ltr">{{ $detail->order_number }}</span></h1>
                <span class="rounded-full bg-canvas px-3 py-1 font-bold text-ink">{{ $detail->status->label() }}</span>
            </div>
            <div class="mt-6"><x-order-timeline :order="$detail" /></div>
            <table class="mt-6 w-full text-sm">
                <thead><tr class="text-muted"><th class="py-2 text-start">المنتج</th><th class="text-start">الكمية</th><th class="text-start">الإجمالي</th></tr></thead>
                <tbody>
                @foreach($detail->items as $item)
                    <tr class="border-t border-wire">
                        <td class="py-2">{{ $item->name }}@if($item->variant_name) — {{ $item->variant_name }}@endif</td>
                        <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit_label_ar }}</td>
                        <td>{{ number_format((float) $item->line_total) }} ج.م</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <dl class="mt-4 grid gap-1 text-sm text-body">
                <div>{{ __('store.subtotal') }}: {{ number_format((float) $detail->subtotal) }} ج.م</div>
                @if((float) $detail->discount_total > 0)<div>الخصم: −{{ number_format((float) $detail->discount_total) }} ج.م</div>@endif
                <div>{{ __('store.shipping') }}: {{ (float) $detail->shipping_fee > 0 ? number_format((float) $detail->shipping_fee).' ج.م' : __('store.free') }}</div>
                <div class="font-extrabold text-ink">{{ __('store.total') }}: {{ number_format((float) $detail->total) }} ج.م</div>
                <div>{{ $detail->governorate }}، {{ $detail->city }}، {{ $detail->address }}</div>
                @if($detail->tracking_number)<div>رقم التتبع: <span dir="ltr">{{ $detail->tracking_number }}</span>@if($detail->shipping_company) ({{ $detail->shipping_company }})@endif</div>@endif
            </dl>
            @if($detail->status === \App\Enums\OrderStatus::New)
                <button wire:click="cancelOrder({{ $detail->id }})" wire:confirm="متأكد من إلغاء الطلب؟" class="mt-5 min-h-11 rounded-lg border border-danger px-4 font-bold text-danger">{{ __('store.cancel_order') }}</button>
            @endif
            {{-- Bank-transfer receipt upload (Phase 10): owner only
                 (this page is already ownership-scoped). --}}
            @if($detail->payment_method === \App\Enums\PaymentMethod::BankTransfer)
                <form method="POST" action="{{ route('orders.receipt.upload', $detail->order_number) }}" enctype="multipart/form-data" class="mt-5 border-t border-wire pt-4">
                    @csrf
                    <label for="account-receipt-file" class="mb-1 block font-semibold text-ink">{{ __('store.upload_receipt') }}</label>
                    <input id="account-receipt-file" type="file" name="receipt" accept="image/*" required class="min-h-11 w-full rounded-lg border border-wire px-3 py-2">
                    @if(session('receipt_uploaded'))<p class="mt-2 font-semibold text-success">{{ __('store.receipt_uploaded') }}</p>@endif
                    @error('receipt')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    <x-btn type="submit" class="mt-3">{{ __('store.upload_receipt') }}</x-btn>
                </form>
            @endif
            <div class="mt-6">
                <a href="{{ route('account.orders') }}" wire:navigate class="font-bold text-link">← {{ __('store.my_orders') }}</a>
            </div>
        </div>
    @else
        <h1 class="mt-6 text-2xl font-extrabold text-ink">{{ __('store.my_orders') }}</h1>
        @if($orders->isEmpty())
            <p class="mt-6 rounded-xl bg-surface p-6 text-center text-muted shadow-sm">{{ __('store.no_orders_yet') }}</p>
        @else
            <div class="mt-4 grid gap-3">
                @foreach($orders as $order)
                    <a href="{{ route('account.orders.show', $order->order_number) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-surface p-4 shadow-sm">
                        <span class="font-bold text-ink" dir="ltr">{{ $order->order_number }}</span>
                        <span class="text-sm text-muted">{{ $order->created_at->format('Y-m-d') }}</span>
                        <span class="rounded-full bg-canvas px-3 py-1 text-sm font-bold text-ink">{{ $order->status->label() }}</span>
                        <span class="font-extrabold text-ink">{{ number_format((float) $order->total) }} ج.م</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    @endif
</div>
