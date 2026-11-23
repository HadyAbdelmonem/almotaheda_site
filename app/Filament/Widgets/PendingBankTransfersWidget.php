<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bank-transfer orders awaiting manual confirmation, with aging
 * (spec §4.10 decision 2: no automatic expiry in v1 — the owner must
 * see how long each transfer has been pending).
 */
class PendingBankTransfersWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'تحويلات بنكية بانتظار التأكيد (بدون انتهاء تلقائي)';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()
                ->where('payment_method', PaymentMethod::BankTransfer)
                ->where('payment_status', PaymentStatus::Pending)
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Returned])
                ->oldest())
            ->columns([
                TextColumn::make('order_number')->label('رقم الطلب'),
                TextColumn::make('customer_name')->label('العميل'),
                TextColumn::make('total')->label('الإجمالي')->money('EGP'),
                TextColumn::make('created_at')->label('منذ')
                    ->formatStateUsing(fn ($state) => $state->diffForHumans()),
            ])
            ->paginated([5]);
    }
}
