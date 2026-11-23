<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Top 5 selling products of the last 30 days, net of returns: only
 * orders currently in delivered status count, so an order that was
 * later returned no longer contributes (spec §7, Phase 6).
 */
class TopProductsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'الأكثر مبيعاً — آخر 30 يوم (صافي بعد المرتجعات)';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OrderItem::query()
                ->select('order_items.product_id')
                ->selectRaw('min(order_items.id) as id')
                ->selectRaw('sum(cast(order_items.quantity as float)) as sold_qty')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Delivered)
                ->where('orders.placed_at', '>=', now()->subDays(30))
                ->whereNotNull('order_items.product_id')
                ->groupBy('order_items.product_id')
                ->orderByDesc('sold_qty')
                ->limit(5))
            ->columns([
                TextColumn::make('product.name')->label('المنتج'),
                TextColumn::make('sold_qty')->label('الكمية المباعة')
                    ->formatStateUsing(fn ($state, OrderItem $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.').' '.($record->unit_label_ar ?? '')),
            ])
            ->paginated(false);
    }
}
