<?php

namespace App\Enums;

enum StockRestorationStatus: string
{
    case NotRequired = 'not_required';
    case Restored = 'restored';
    case PendingInspection = 'pending_inspection';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'غير مطلوب',
            self::Restored => 'تمت الإعادة للمخزون',
            self::PendingInspection => 'بانتظار الفحص',
            self::WrittenOff => 'تم الإتلاف',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotRequired => 'gray',
            self::Restored => 'green',
            self::PendingInspection => 'amber',
            self::WrittenOff => 'red',
        };
    }
}
