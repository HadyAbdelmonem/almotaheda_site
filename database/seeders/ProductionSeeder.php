<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production seeding (spec Phase 1B step 9): essential reference data
 * only — settings, governorates, category tree, brands and pages.
 * NEVER creates demo products, demo coupons/banners, or an admin user
 * with a known/random printed password.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            ShippingRateSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            PageSeeder::class,
        ]);
    }
}
