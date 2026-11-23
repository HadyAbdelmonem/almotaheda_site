<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/** Demo brands (spec §5). ABB / شنايدر / بوش marked as authorized dealers for demo. */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['ABB', 'abb', true],
            ['شنايدر', 'schneider', true],
            ['بوش', 'bosch', true],
            ['سانشي', 'sanchi', false],
            ['أوسا', 'osa', false],
            ['سنجر', 'singer', false],
            ['MK', 'mk', false],
        ];

        foreach ($brands as $i => [$name, $slug, $dealer]) {
            Brand::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_authorized_dealer' => $dealer, 'is_active' => true, 'sort_order' => $i + 1]
            );
        }
    }
}
