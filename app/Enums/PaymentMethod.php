<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'الدفع عند الاستلام',
            self::BankTransfer => 'تحويل بنكي',
            self::Card => 'بطاقة ائتمان',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cod => 'green',
            self::BankTransfer => 'blue',
            self::Card => 'violet',
        };
    }
}
