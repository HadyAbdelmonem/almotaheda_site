<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * ~30 realistic DEMO products (spec §5 seed data). No real images and
 * no real store claims: prices/stock are demo placeholders only.
 * Includes: 5 variant products, 3 tiered products, a meter-sold cable
 * with a fractional step, a roll-only product, sale / low-stock /
 * out-of-stock examples, and technical attribute values.
 */
class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        // [name, model, brand slug, category slug, price, sale|null, stock, unit, step]
        $rows = [
            ['قاطع أحادي 16 أمبير منحنى C', 'S201C16', 'abb', 'breaker-panels', 185, 149, 120, 'piece', 1],
            ['قاطع ثنائي 32 أمبير منحنى C', 'S202C32', 'abb', 'breaker-panels', 340, null, 80, 'piece', 1],
            ['قاطع ثلاثي 63 أمبير', 'S203C63', 'schneider', 'breaker-panels', 720, null, 45, 'piece', 1],
            ['لوحة قواطع 12 خط معدن', 'LP-12M', 'sanchi', 'electrical-panels', 1450, 1299, 25, 'piece', 1],
            ['لوحة قواطع 24 خط معدن', 'LP-24M', 'sanchi', 'electrical-panels', 2350, null, 4, 'piece', 1], // low stock
            ['سلك نحاس معزول 1.5 مم — بالمتر', 'CU-1.5-PVC', null, 'wires-cables', 18, null, 850.5, 'meter', 0.25],
            ['سلك نحاس معزول 2.5 مم — بالمتر', 'CU-2.5-PVC', null, 'wires-cables', 26, 22, 640, 'meter', 0.25],
            ['كابل نحاس 3×6 مم — بالمتر', 'CU-3X6', null, 'wires-cables', 145, null, 320, 'meter', 0.5],
            ['لفة سلك 2.5 مم 100 متر', 'ROLL-2.5-100', null, 'wires-cables', 2400, null, 30, 'roll', 1],
            ['مواسير كهرباء 16 مم — بالمتر', 'PIPE-16', null, 'wires-cables', 12, null, 500, 'meter', 1],
            ['مفتاح إضاءة مفرد', 'SW-1G', 'mk', 'switches-sockets', 95, null, 200, 'piece', 1],
            ['بريزة مزدوجة 16 أمبير', 'SK-2G', 'mk', 'switches-sockets', 140, 119, 150, 'piece', 1],
            ['مفتاح سخان 32 أمبير', 'SW-HT32', 'osa', 'switches-sockets', 210, null, 60, 'piece', 1],
            ['بريزة USB مزدوجة', 'SK-USB2', 'osa', 'switches-sockets', 320, null, 0, 'piece', 1], // out of stock
            ['لمبة ليد 9 وات E27', 'LED-9W-E27', 'singer', 'led-bulbs', 65, 49, 400, 'piece', 1],
            ['لمبة ليد 12 وات E27', 'LED-12W-E27', 'singer', 'led-bulbs', 85, null, 350, 'piece', 1],
            ['سبوت ليد 7 وات', 'SPOT-7W', null, 'chandeliers-spots', 110, null, 180, 'piece', 1],
            ['بروفايل ليد ألومنيوم — بالمتر', 'PROF-LED-2M', null, 'led-profile-garden', 95, null, 220, 'meter', 0.5],
            ['بريزة صاروخ 32 أمبير صناعية', 'IND-SK32', 'sanchi', 'rocket-sockets', 260, null, 70, 'piece', 1],
            ['شفاط صناعي 12 بوصة', 'FAN-IND12', 'singer', 'industrial-fans', 1150, 999, 18, 'piece', 1],
            ['فيوز سيراميك 10 أمبير (عبوة 10)', 'FUSE-10A-10', null, 'fuses', 45, null, 300, 'pack', 1],
            ['حساس حركة سقفي', 'MS-360', null, 'motion-sensors', 230, null, 90, 'piece', 1],
            ['وحدة تحكم إضاءة ذكية واي فاي', 'SM-SW4', null, 'lighting-control', 540, 469, 40, 'piece', 1],
            ['كاميرا IP واي فاي 3 ميجا', 'CAM-W3MP', null, 'ip-cameras', 1250, null, 55, 'piece', 1],
            ['كاميرا IP خارجية 5 ميجا PoE', 'CAM-P5MP', null, 'ip-cameras', 2100, null, 22, 'piece', 1],
            ['انتركم مرئي 7 بوصة', 'INT-V7', null, 'intercom', 3350, null, 12, 'piece', 1],
            ['مشترك 4 عين بحماية', 'PS-4P', 'osa', 'power-strips', 175, 149, 130, 'piece', 1],
            ['استابليزر 5 كيلو', 'STAB-5K', 'sanchi', 'stabilizers', 4900, null, 8, 'piece', 1],
            ['دريل شاكوش 800 وات', 'DR-800H', 'bosch', 'drills-grinders', 2850, 2499, 28, 'piece', 1],
            ['صاروخ تجليخ 4.5 بوصة 720 وات', 'AG-720', 'bosch', 'drills-grinders', 1650, null, 35, 'piece', 1],
            ['طقم أقراص تقطيع (عبوة 10)', 'DISC-C10', 'bosch', 'cutting-discs', 120, null, 500, 'pack', 1],
            ['جهاز قياس متعدد رقمي', 'MM-DT830', null, 'measuring-laser', 380, null, 75, 'piece', 1],
            ['دفاية هالوجين 4 شمعة', 'HT-4H', 'singer', 'heaters', 690, 590, 3, 'piece', 1], // low stock
            ['شفاط مطبخ 25 سم', 'FAN-K25', 'singer', 'home-fans', 520, null, 48, 'piece', 1],
            ['صاعق ناموس كهربائي', 'MZ-20W', null, 'mosquito-zappers', 340, null, 65, 'piece', 1],
        ];

        $products = [];
        foreach ($rows as $i => [$name, $model, $brandSlug, $catSlug, $price, $sale, $stock, $unit, $step]) {
            $products[$model] = Product::updateOrCreate(
                ['model_code' => $model],
                [
                    'name' => $name,
                    'slug' => strtolower($model),
                    'brand_id' => $brandSlug ? Brand::where('slug', $brandSlug)->value('id') : null,
                    'category_id' => Category::where('slug', $catSlug)->value('id'),
                    'short_description' => 'منتج تجريبي — وصف مختصر قابل للتعديل',
                    'description' => 'منتج تجريبي لأغراض العرض فقط — الوصف الكامل قابل للتعديل من لوحة التحكم.',
                    'unit_code' => $unit,
                    'min_order_quantity' => 1,
                    'quantity_step' => $step,
                    'cost_price' => round($price * 0.7, 2),
                    'price' => $price,
                    'sale_price' => $sale,
                    'sale_ends_at' => $sale !== null ? now()->addDays(14) : null,
                    'stock_quantity' => $stock,
                    'track_stock' => true,
                    'is_active' => true,
                    'is_featured' => $i < 6,
                    'sales_count' => ($i * 37) % 140,
                ]
            );
        }

        $this->seedVariants($products);
        $this->seedTiers($products);
        $this->seedAttributeValues($products);
    }

    /** Five switch/socket products get white/black/gold variants (spec §5). */
    private function seedVariants(array $products): void
    {
        $variantModels = ['SW-1G', 'SK-2G', 'SW-HT32', 'SK-USB2', 'PS-4P'];
        $colors = [['أبيض', 'W'], ['أسود', 'B'], ['ذهبي', 'G']];

        foreach ($variantModels as $model) {
            $product = $products[$model] ?? null;
            if ($product === null) {
                continue;
            }
            $product->update(['has_variants' => true, 'stock_quantity' => 0]);
            foreach ($colors as $sort => [$colorName, $suffix]) {
                $product->variants()->updateOrCreate(
                    ['sku' => $model.'-'.$suffix],
                    [
                        'name' => $colorName,
                        'options' => ['اللون' => $colorName],
                        'price' => null,
                        'stock_quantity' => 20 + $sort * 5,
                        'is_active' => true,
                        'sort_order' => $sort,
                    ]
                );
            }
        }
    }

    /** Three products get quantity tiers (spec §5). */
    private function seedTiers(array $products): void
    {
        $tiered = [
            'LED-9W-E27' => [[10, 5], [25, 10]],
            'CU-2.5-PVC' => [[50, 5], [100, 10]],
            'FUSE-10A-10' => [[5, 5], [20, 12]],
        ];

        foreach ($tiered as $model => $tiers) {
            $product = $products[$model] ?? null;
            if ($product === null) {
                continue;
            }
            foreach ($tiers as [$minQty, $percent]) {
                $product->priceTiers()->updateOrCreate(
                    ['min_qty' => $minQty],
                    ['discount_percent' => $percent]
                );
            }
        }
    }

    /** Believable demo technical values for the seeded attributes (§4.12). */
    private function seedAttributeValues(array $products): void
    {
        $values = [
            // model => [attribute key => [type, value]]
            'S201C16' => ['current_rating_a' => ['number', 16], 'poles' => ['text', '1'], 'trip_curve' => ['text', 'C'], 'breaking_capacity_ka' => ['number', 6]],
            'S202C32' => ['current_rating_a' => ['number', 32], 'poles' => ['text', '2'], 'trip_curve' => ['text', 'C'], 'breaking_capacity_ka' => ['number', 6]],
            'CU-1.5-PVC' => ['cross_section_mm2' => ['number', 1.5], 'cores' => ['number', 1], 'conductor_material' => ['text', 'copper'], 'insulation_type' => ['text', 'pvc']],
            'CU-2.5-PVC' => ['cross_section_mm2' => ['number', 2.5], 'cores' => ['number', 1], 'conductor_material' => ['text', 'copper'], 'insulation_type' => ['text', 'pvc']],
            'LED-9W-E27' => ['wattage_w' => ['number', 9], 'lumen' => ['number', 800], 'color_temperature_k' => ['number', 6500], 'base_type' => ['text', 'e27']],
            'LED-12W-E27' => ['wattage_w' => ['number', 12], 'lumen' => ['number', 1100], 'color_temperature_k' => ['number', 4000], 'base_type' => ['text', 'e27']],
            'DR-800H' => ['power_w' => ['number', 800], 'voltage_v' => ['number', 220], 'chuck_mm' => ['number', 13]],
            'AG-720' => ['power_w' => ['number', 720], 'voltage_v' => ['number', 220]],
            'CAM-W3MP' => ['resolution_mp' => ['number', 3], 'connectivity' => ['text', 'wifi'], 'night_vision' => ['boolean', true]],
            'CAM-P5MP' => ['resolution_mp' => ['number', 5], 'connectivity' => ['text', 'poe'], 'night_vision' => ['boolean', true]],
        ];

        foreach ($values as $model => $attrs) {
            $product = $products[$model] ?? null;
            if ($product === null) {
                continue;
            }
            foreach ($attrs as $key => [$type, $value]) {
                $attribute = CategoryAttribute::where('key', $key)
                    ->where('category_id', $product->category_id)
                    ->first();
                if ($attribute === null) {
                    continue;
                }
                $product->attributeValues()->updateOrCreate(
                    ['category_attribute_id' => $attribute->id],
                    [
                        'value_text' => $type === 'text' ? (string) $value : null,
                        'value_number' => $type === 'number' ? $value : null,
                        'value_boolean' => $type === 'boolean' ? (bool) $value : null,
                    ]
                );
            }
        }
    }
}
