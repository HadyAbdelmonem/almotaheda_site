<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

/**
 * The 27 governorates (spec §5.2). Fees are PLACEHOLDERS per §4.4:
 * Cairo/Giza 50, Alexandria 60, Delta & Canal 65, Upper Egypt 75,
 * Sinai/Red Sea/New Valley/Matrouh 90. The owner edits them in admin.
 */
class ShippingRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            ['القاهرة', 50], ['الجيزة', 50], ['الإسكندرية', 60],
            ['القليوبية', 65], ['الشرقية', 65], ['الدقهلية', 65], ['البحيرة', 65],
            ['المنوفية', 65], ['الغربية', 65], ['كفر الشيخ', 65], ['دمياط', 65],
            ['بورسعيد', 65], ['الإسماعيلية', 65], ['السويس', 65],
            ['شمال سيناء', 90], ['جنوب سيناء', 90],
            ['الفيوم', 75], ['بني سويف', 75], ['المنيا', 75], ['أسيوط', 75],
            ['سوهاج', 75], ['قنا', 75], ['الأقصر', 75], ['أسوان', 75],
            ['البحر الأحمر', 90], ['الوادي الجديد', 90], ['مطروح', 90],
        ];

        foreach ($rates as $i => [$name, $fee]) {
            ShippingRate::updateOrCreate(
                ['name_ar' => $name],
                ['fee' => $fee, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => $i + 1]
            );
        }
    }
}
