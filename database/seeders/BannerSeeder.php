<?php

namespace Database\Seeders;

use App\Enums\BannerPosition;
use App\Models\Banner;
use Illuminate\Database\Seeder;

/**
 * Demo banners: 3 hero + 3 promo (spec §5). No external images are
 * downloaded; image paths stay null and the storefront renders the
 * generated placeholder until the owner uploads real images.
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            ['عروض التأسيس', BannerPosition::Hero],
            ['أدوات كهربائية أصلية', BannerPosition::Hero],
            ['إنارة ليد موفرة', BannerPosition::Hero],
            ['عرض الأسبوع', BannerPosition::Promo],
            ['عدد ومعدات', BannerPosition::Promo],
            ['سمارت هوم', BannerPosition::Promo],
        ];

        foreach ($banners as $i => [$title, $position]) {
            Banner::updateOrCreate(
                ['title' => $title, 'position' => $position],
                ['is_active' => true, 'sort_order' => $i + 1]
            );
        }
    }
}
