<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

/** Demo opening coupon OPEN10: 10% off, active (spec §5). */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::updateOrCreate(
            ['code' => 'OPEN10'],
            ['type' => CouponType::Percent, 'value' => 10, 'min_order' => 0, 'is_active' => true]
        );
    }
}
