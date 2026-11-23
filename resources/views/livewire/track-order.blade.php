<div class="mx-auto max-w-2xl px-4 py-10">
    <h1 class="text-2xl font-extrabold text-ink">{{ __('store.track_order') }}</h1>
    <p class="mt-1 text-muted">{{ __('store.track_order_hint') }}</p>
    <form wire:submit="track" class="mt-6 grid gap-4 rounded-xl bg-surface p-5 shadow-sm">
        <div>
            <label for="order_number" class="mb-1 block font-semibold text-ink">{{ __('store.order_number') }}</label>
            <input id="order_number" wire:model="orderNumber" class="min-h-11 w-full rounded-lg border border-wire px-3" dir="ltr" placeholder="ORD-2026-00001">
            @error('orderNumber') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="mb-1 block font-semibold text-ink">{{ __('store.phone') }}</label>
            <input id="phone" wire:model="phone" class="min-h-11 w-full rounded-lg border border-wire px-3" dir="ltr">
            @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <x-btn type="submit" class="w-fit">{{ __('store.track') }}</x-btn>
    </form>

    @if($searched)
        @if($found)
            <section class="mt-6 rounded-xl bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-extrabold text-ink"><span dir="ltr">{{ $found->order_number }}</span></h2>
                    <span class="rounded-full bg-canvas px-3 py-1 font-bold text-ink">{{ $found->status->label() }}</span>
                </div>
                <div class="mt-5"><x-order-timeline :order="$found" /></div>
                <ul class="mt-4 grid gap-1 text-sm text-body">
                    @foreach($found->items as $item)
                        <li>{{ $item->name }} × {{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit_label_ar }} — {{ number_format((float) $item->line_total) }} ج.م</li>
                    @endforeach
                </ul>
                <p class="mt-3 font-extrabold text-ink">{{ __('store.total') }}: {{ number_format((float) $found->total) }} ج.م</p>

                {{-- Bank-transfer receipt upload (Phase 10): the phone
                     already matched this order, which is the guest's
                     proof of access. The receipt never changes payment
                     status by itself. --}}
                @if($found->payment_method === \App\Enums\PaymentMethod::BankTransfer)
                    <form method="POST" action="{{ route('orders.receipt.upload', $found->order_number) }}" enctype="multipart/form-data" class="mt-5 border-t border-wire pt-4">
                        @csrf
                        <input type="hidden" name="phone" value="{{ $phone }}">
                        <label for="receipt-file" class="mb-1 block font-semibold text-ink">{{ __('store.upload_receipt') }}</label>
                        <input id="receipt-file" type="file" name="receipt" accept="image/*" required class="min-h-11 w-full rounded-lg border border-wire px-3 py-2">
                        @if(session('receipt_uploaded'))<p class="mt-2 font-semibold text-success">{{ __('store.receipt_uploaded') }}</p>@endif
                        @error('receipt')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <x-btn type="submit" class="mt-3">{{ __('store.upload_receipt') }}</x-btn>
                    </form>
                @endif
            </section>
        @else
            <p class="mt-6 rounded-xl bg-danger/10 p-4 font-bold text-danger" role="alert">{{ __('store.track_mismatch') }}</p>
        @endif
    @endif
</div>
