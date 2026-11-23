<?php

namespace App\Enums;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'نسبة مئوية',
            self::Fixed => 'مبلغ ثابت',
            self::FreeShipping => 'شحن مجاني',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percent => 'blue',
            self::Fixed => 'green',
            self::FreeShipping => 'amber',
        };
    }
}
