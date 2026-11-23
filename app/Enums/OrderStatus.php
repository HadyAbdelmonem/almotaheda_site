<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Confirmed => 'مؤكد',
            self::Processing => 'قيد التجهيز',
            self::Shipped => 'تم الشحن',
            self::Delivered => 'تم التوصيل',
            self::Cancelled => 'ملغي',
            self::Returned => 'مرتجع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Confirmed => 'blue',
            self::Processing => 'amber',
            self::Shipped => 'violet',
            self::Delivered => 'green',
            self::Cancelled => 'red',
            self::Returned => 'orange',
        };
    }
}
