<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Low-stock products using each product's effective threshold (spec §7). */
class LowStockWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'مخزون منخفض';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $default = (float) setting('low_stock_default', 5);

        return $table
            ->query(fn (): Builder => Product::query()
                ->where('track_stock', true)
                ->where('is_active', true)
                ->where(function (Builder $q) use ($default) {
                    $q->whereRaw('low_stock_threshold is not null and cast(stock_quantity as float) <= cast(low_stock_threshold as float)')
                        ->orWhereRaw('low_stock_threshold is null and cast(stock_quantity as float) <= ?', [$default]);
                })
                ->orderBy('stock_quantity'))
            ->columns([
                TextColumn::make('name')->label('المنتج'),
                TextColumn::make('model_code')->label('كود الموديل'),
                TextColumn::make('stock_quantity')->label('المخزون الحالي')
                    ->formatStateUsing(fn ($state, Product $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.').' '.unit_label($record->unit_code)),
                TextColumn::make('low_stock_threshold')->label('حد التنبيه')
                    ->formatStateUsing(fn ($state) => $state !== null ? (string) (float) $state : (string) (float) setting('low_stock_default', 5)),
            ])
            ->paginated([5]);
    }
}
