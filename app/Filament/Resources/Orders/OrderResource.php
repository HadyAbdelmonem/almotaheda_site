<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockRestorationStatus;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Orders admin (spec §7, Phase 6). Status changes go ONLY through
 * OrderService::changeStatus; stock disposition ONLY through
 * OrderService::resolveStockDisposition; manual paid marking ONLY
 * for bank transfers via OrderService::markBankTransferPaid.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static string|UnitEnum|null $navigationGroup = 'الطلبات';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', OrderStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        // Only logistics/internal fields are editable directly; status,
        // payment and totals change exclusively through services.
        return $schema->components([
            TextInput::make('shipping_company')->label('شركة الشحن'),
            TextInput::make('tracking_number')->label('رقم التتبع'),
            Textarea::make('admin_notes')->label('ملاحظات داخلية')->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الطلب')->schema([
                TextEntry::make('order_number')->label('رقم الطلب'),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                TextEntry::make('payment_method')->label('طريقة الدفع')
                    ->formatStateUsing(fn (PaymentMethod $state) => $state->label()),
                TextEntry::make('payment_status')->label('حالة الدفع')->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => $state->color()),
                TextEntry::make('placed_at')->label('تاريخ الطلب')->dateTime('Y-m-d H:i'),
                TextEntry::make('payment_reconciliation_required')->label('يحتاج تسوية دفع')
                    ->formatStateUsing(fn ($state) => $state ? 'نعم' : 'لا'),
            ])->columns(3),
            Section::make('العميل والعنوان')->schema([
                TextEntry::make('customer_name')->label('الاسم'),
                TextEntry::make('phone')->label('الموبايل'),
                TextEntry::make('phone_alt')->label('موبايل بديل'),
                TextEntry::make('email')->label('البريد'),
                TextEntry::make('governorate')->label('المحافظة'),
                TextEntry::make('city')->label('المدينة'),
                TextEntry::make('address')->label('العنوان'),
                TextEntry::make('notes')->label('ملاحظات العميل'),
            ])->columns(3),
            Section::make('بنود الطلب (لقطات ثابتة)')->schema([
                RepeatableEntry::make('items')->label('')
                    ->schema([
                        TextEntry::make('name')->label('المنتج'),
                        TextEntry::make('variant_name')->label('الشكل'),
                        TextEntry::make('sku')->label('SKU'),
                        TextEntry::make('quantity')->label('الكمية')
                            ->formatStateUsing(fn ($state, $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.').' '.$record->unit_label_ar),
                        TextEntry::make('unit_price')->label('سعر الوحدة')->money('EGP'),
                        TextEntry::make('line_total')->label('الإجمالي')->money('EGP'),
                    ])->columns(6),
            ]),
            Section::make('الإجماليات والشحن')->schema([
                TextEntry::make('subtotal')->label('المجموع الفرعي')->money('EGP'),
                TextEntry::make('discount_total')->label('الخصم')->money('EGP'),
                TextEntry::make('coupon_code')->label('الكوبون'),
                TextEntry::make('shipping_fee')->label('الشحن')->money('EGP'),
                TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                TextEntry::make('shipping_company')->label('شركة الشحن'),
                TextEntry::make('tracking_number')->label('رقم التتبع'),
                TextEntry::make('admin_notes')->label('ملاحظات داخلية'),
            ])->columns(3),
            Section::make('حالة المخزون بعد الإلغاء/الارتجاع')->schema([
                TextEntry::make('stock_restoration_status')->label('حالة تصرف المخزون')
                    ->formatStateUsing(fn (StockRestorationStatus $state) => $state->label()),
                TextEntry::make('stock_restored_at')->label('تاريخ الإعادة')->dateTime('Y-m-d H:i'),
                TextEntry::make('stock_disposition_note')->label('ملاحظة الفحص'),
                TextEntry::make('stockDispositionBy.name')->label('بواسطة'),
            ])->columns(2),
            Section::make('محاولات الدفع')->schema([
                RepeatableEntry::make('paymentTransactions')->label('')
                    ->schema([
                        TextEntry::make('provider')->label('المزود'),
                        TextEntry::make('provider_transaction_id')->label('رقم العملية'),
                        TextEntry::make('amount')->label('المبلغ')->money('EGP'),
                        TextEntry::make('status')->label('الحالة'),
                        TextEntry::make('refund_reference')->label('مرجع الاسترداد'),
                    ])->columns(5),
            ]),
            Section::make('سجل الحالات')->schema([
                RepeatableEntry::make('statusHistories')->label('')
                    ->schema([
                        TextEntry::make('from_status')->label('من')
                            ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : '—'),
                        TextEntry::make('to_status')->label('إلى')
                            ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : (string) $state),
                        TextEntry::make('changedBy.name')->label('بواسطة'),
                        TextEntry::make('note')->label('ملاحظة'),
                        TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
                    ])->columns(5),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')->label('رقم الطلب')->searchable()->sortable(),
                TextColumn::make('customer_name')->label('العميل')->searchable(),
                TextColumn::make('phone')->label('الموبايل')->searchable(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('payment_method')->label('الدفع')
                    ->formatStateUsing(fn (PaymentMethod $state) => $state->label()),
                TextColumn::make('payment_status')->label('حالة الدفع')->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => $state->color()),
                TextColumn::make('total')->label('الإجمالي')->money('EGP')->sortable(),
                TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('payment_status')->label('حالة الدفع')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('payment_method')->label('طريقة الدفع')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $s) => [$s->value => $s->label()])->all()),
                Filter::make('created_at')->label('تاريخ الطلب')
                    ->schema([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                self::changeStatusAction(),
                self::whatsappAction(),
                self::invoiceAction(),
                self::markBankPaidAction(),
                self::resolveDispositionAction(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('تغيير الحالة')
            ->icon(Heroicon::OutlinedArrowPath)
            ->schema(fn (Order $record) => [
                Select::make('to_status')
                    ->label('الحالة الجديدة')
                    ->options(collect(OrderStatus::cases())
                        ->filter(fn (OrderStatus $s) => app(OrderService::class)->canTransition($record->status, $s))
                        ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
                        ->all())
                    ->required(),
                Textarea::make('note')->label('ملاحظة (اختياري)'),
            ])
            ->visible(fn (Order $record) => collect(OrderStatus::cases())->contains(fn (OrderStatus $s) => app(OrderService::class)->canTransition($record->status, $s)))
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderService::class)->changeStatus($record, OrderStatus::from($data['to_status']), auth()->user(), $data['note'] ?? null);
                    Notification::make()->title('تم تغيير حالة الطلب')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('تعذر تغيير الحالة')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function whatsappAction(): Action
    {
        return Action::make('whatsapp')
            ->label('واتساب')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->url(fn (Order $record) => 'https://wa.me/'.Phone::toWhatsApp($record->phone).'?text='.urlencode('أهلًا '.$record->customer_name.'، بنأكد معاك طلبك رقم '.$record->order_number.' بقيمة '.number_format((float) $record->total).' ج.م. هنجهزه ونبلغك بموعد الشحن.'))
            ->openUrlInNewTab();
    }

    public static function invoiceAction(): Action
    {
        return Action::make('invoice')
            ->label('طباعة الفاتورة')
            ->icon(Heroicon::OutlinedPrinter)
            ->url(fn (Order $record) => route('orders.invoice', $record))
            ->openUrlInNewTab();
    }

    /** View the privately stored bank-transfer receipt (Phase 10). */
    public static function receiptAction(): Action
    {
        return Action::make('receipt')
            ->label('عرض إيصال التحويل')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (Order $record) => $record->payment_proof !== null)
            ->url(fn (Order $record) => route('orders.receipt.show', $record))
            ->openUrlInNewTab();
    }

    public static function markBankPaidAction(): Action
    {
        return Action::make('markBankPaid')
            ->label('تأكيد استلام التحويل')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (Order $record) => $record->payment_method === PaymentMethod::BankTransfer && $record->payment_status === PaymentStatus::Pending)
            ->schema([
                Textarea::make('note')->label('ملاحظة التحقق من الاستلام (إلزامية للتدقيق)')->required(),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderService::class)->markBankTransferPaid($record, auth()->user(), (string) $data['note']);
                    Notification::make()->title('تم تأكيد استلام التحويل البنكي')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('تعذر تأكيد الدفع')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function resolveDispositionAction(): Action
    {
        return Action::make('resolveDisposition')
            ->label('حسم تصرف المخزون')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->visible(fn (Order $record) => $record->stock_restoration_status === StockRestorationStatus::PendingInspection)
            ->schema([
                Select::make('decision')
                    ->label('قرار الفحص بعد الاستلام الفعلي')
                    ->options([
                        StockRestorationStatus::Restored->value => 'إعادة للمخزون (سليم وقابل للبيع)',
                        StockRestorationStatus::WrittenOff->value => 'إتلاف / عدم إعادة للمخزون',
                    ])
                    ->required(),
                Textarea::make('note')->label('ملاحظة الفحص (إلزامية)')->required(),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderService::class)->resolveStockDisposition($record, StockRestorationStatus::from($data['decision']), auth()->user(), (string) $data['note']);
                    Notification::make()->title('تم حسم تصرف المخزون')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('تعذر حسم التصرف')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
