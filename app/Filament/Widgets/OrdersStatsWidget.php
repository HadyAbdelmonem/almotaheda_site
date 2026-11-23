<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard headline stats (spec §7, Phase 6). */
class OrdersStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Order::query()->whereDate('created_at', today());
        $todayRevenue = (clone $today)->where('status', '!=', OrderStatus::Cancelled)->sum('total');

        return [
            Stat::make('طلبات اليوم', (clone $today)->count()),
            Stat::make('إيراد اليوم', number_format((float) $todayRevenue).' ج.م'),
            Stat::make('طلبات جديدة', Order::query()->where('status', OrderStatus::New)->count()),
            Stat::make('تحويلات بنكية بانتظار التأكيد', Order::query()
                ->where('payment_method', PaymentMethod::BankTransfer)
                ->where('payment_status', PaymentStatus::Pending)
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Returned])
                ->count()),
        ];
    }
}
