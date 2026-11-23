<?php

namespace App\Enums;

enum BannerPosition: string
{
    case Hero = 'hero';
    case Promo = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'الشريط الرئيسي',
            self::Promo => 'عروض ترويجية',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hero => 'blue',
            self::Promo => 'amber',
        };
    }
}
