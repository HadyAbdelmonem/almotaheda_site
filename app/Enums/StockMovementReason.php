<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case OrderPlaced = 'order_placed';
    case OrderCancelled = 'order_cancelled';
    case OrderReturned = 'order_returned';
    case ManualAdjustment = 'manual_adjustment';
    case Import = 'import';
    case ReturnWrittenOff = 'return_written_off';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'طلب جديد',
            self::OrderCancelled => 'إلغاء طلب',
            self::OrderReturned => 'مرتجع طلب',
            self::ManualAdjustment => 'تعديل يدوي',
            self::Import => 'استيراد',
            self::ReturnWrittenOff => 'إتلاف مرتجع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OrderPlaced => 'blue',
            self::OrderCancelled => 'red',
            self::OrderReturned => 'orange',
            self::ManualAdjustment => 'gray',
            self::Import => 'violet',
            self::ReturnWrittenOff => 'red',
        };
    }
}
