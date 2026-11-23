<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فاتورة {{ $order->order_number }}</title>
    <style>
        body { font-family: 'Cairo', system-ui, sans-serif; color: #111827; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #CBD5E1; padding: 8px; text-align: right; font-size: 14px; }
        th { background: #F5F6F8; }
        .totals { width: 50%; margin-inline-start: auto; }
        .muted { color: #6B7280; font-size: 13px; }
        @media print {
            body { margin: 0; }
            @page { size: A4; margin: 14mm; }
            * { animation: none !important; transition: none !important; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {{-- Commercial order receipt — NOT an Egyptian Tax Authority e-invoice (spec §7.2). --}}
    <h1>{{ setting('store_name', __('store.name')) }}</h1>
    @if(setting('address'))<p class="muted">{{ setting('address') }}</p>@endif
    @if(setting('tax_number'))<p class="muted">الرقم الضريبي: {{ setting('tax_number') }}</p>@endif
    @if(setting('store_tax_note'))<p class="muted">{{ setting('store_tax_note') }}</p>@endif

    <h2>فاتورة طلب {{ $order->order_number }}</h2>
    <p class="muted">التاريخ: {{ $order->placed_at?->format('Y-m-d H:i') ?? $order->created_at->format('Y-m-d H:i') }}</p>

    <h3>بيانات العميل</h3>
    <p>{{ $order->customer_name }} — <span dir="ltr">{{ $order->phone }}</span><br>
       {{ $order->governorate }}، {{ $order->city }}، {{ $order->address }}</p>

    <table>
        <thead>
            <tr><th>المنتج</th><th>SKU</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->name }}@if($item->variant_name) — {{ $item->variant_name }}@endif</td>
                    <td dir="ltr">{{ $item->sku }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit_label_ar }}</td>
                    <td>{{ number_format((float) $item->unit_price) }} ج.م</td>
                    <td>{{ number_format((float) $item->line_total) }} ج.م</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><th>المجموع الفرعي</th><td>{{ number_format((float) $order->subtotal) }} ج.م</td></tr>
        @if((float) $order->discount_total > 0)
            <tr><th>الخصم @if($order->coupon_code) (كوبون {{ $order->coupon_code }}) @endif</th><td>−{{ number_format((float) $order->discount_total) }} ج.م</td></tr>
        @endif
        <tr><th>الشحن</th><td>{{ (float) $order->shipping_fee > 0 ? number_format((float) $order->shipping_fee).' ج.م' : 'مجاني' }}</td></tr>
        <tr><th>الإجمالي</th><td><strong>{{ number_format((float) $order->total) }} ج.م</strong></td></tr>
    </table>

    <p>طريقة الدفع: {{ $order->payment_method->label() }} — حالة الدفع: {{ $order->payment_status->value === 'paid' ? 'مدفوع' : 'قيد الانتظار' }}</p>
    <p>شكراً لثقتكم</p>
    <p class="muted">هذا إيصال شراء تجاري وليس فاتورة ضريبية إلكترونية.</p>
    <button class="no-print" onclick="window.print()">طباعة</button>
</body>
</html>
