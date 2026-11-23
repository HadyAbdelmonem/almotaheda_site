<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->label('الصورة')
                    ->collection('gallery')
                    ->conversion('thumb'),
                TextColumn::make('name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('model_code')
                    ->label('كود الموديل')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('التصنيف'),
                TextColumn::make('brand.name')
                    ->label('الماركة')
                    ->placeholder('-'),
                TextColumn::make('price')
                    ->label('السعر')
                    ->money('EGP'),
                TextColumn::make('stock_quantity')
                    ->label('المخزون')
                    ->formatStateUsing(fn ($state, Product $record) => rtrim(rtrim((string) $state, '0'), '.').' '.unit_label($record->unit_code))
                    ->badge()
                    ->color(fn (Product $record) => ! $record->track_stock ? 'gray' : ((float) $record->stock_quantity <= 0 ? 'danger' : ((float) $record->stock_quantity <= (float) $record->effectiveLowStockThreshold() ? 'warning' : 'success'))),
                IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->label('مميز')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name'),
                SelectFilter::make('brand_id')
                    ->label('الماركة')
                    ->relationship('brand', 'name'),
                TernaryFilter::make('is_active')->label('مفعّل'),
                Filter::make('low_stock')
                    ->label('مخزون منخفض')
                    ->query(function (Builder $query) {
                        $default = (float) setting('low_stock_default', 5);
                        $query->where('track_stock', true)
                            ->where(function (Builder $q) use ($default) {
                                $q->whereRaw('low_stock_threshold is not null and cast(stock_quantity as float) <= cast(low_stock_threshold as float)')
                                    ->orWhereRaw('low_stock_threshold is null and cast(stock_quantity as float) <= ?', [$default]);
                            });
                    }),
            ])
            ->recordActions([
                Action::make('adjustStock')
                    ->label('تعديل المخزون')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        TextInput::make('change')
                            ->label('التغيير (+ إضافة / - خصم)')
                            ->required()
                            ->helperText('باستخدام وحدة بيع المنتج، وقد يكون كسرياً للمنتجات بالمتر.'),
                        Textarea::make('note')
                            ->label('سبب التعديل')
                            ->required(),
                    ])
                    ->action(function (Product $record, array $data) {
                        try {
                            DB::transaction(fn () => app(StockService::class)->adjust($record, null, (string) $data['change'], (string) $data['note']));
                            Notification::make()->title('تم تعديل المخزون وتسجيل الحركة')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('تعذر تعديل المخزون')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('تفعيل المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                    BulkAction::make('deactivate')
                        ->label('إيقاف المحدد')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
