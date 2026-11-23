<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full demo seeding for local development. For production use
 * ProductionSeeder instead (no demo products, no printed admin password).
 */
class DatabaseSeeder extends Seeder
{
    // Note: model events must stay enabled — Product's saving hook fills
    // catalog_identity and search_text (spec §5).
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            ShippingRateSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            PageSeeder::class,
            CouponSeeder::class,
            AdminUserSeeder::class,
            DemoProductSeeder::class,
            BannerSeeder::class,
        ]);
    }
}
