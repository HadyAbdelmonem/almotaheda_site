<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\Seeder;

/**
 * Category tree (spec §5.1) with icon keys (§11.7), plus the technical
 * attribute definitions of §4.12 for breakers, cables, lighting,
 * power tools and cameras.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // slug => [name, icon, children: slug => [name, icon]]
        $tree = [
            'foundation' => ['تأسيس كهرباء', 'breaker', [
                'breaker-panels' => ['لوح قواطع', null],
                'wire-supplies' => ['لوازم أسلاك', null],
                'electrical-panels' => ['لوحات كهربائية', null],
                'wires-cables' => ['أسلاك وكابلات ومواسير', 'cable'],
            ]],
            'finishing' => ['تشطيب كهرباء', 'plug-socket', [
                'switches-sockets' => ['مفاتيح وبرايز', null],
            ]],
            'lighting-decor' => ['إنارة وديكور', 'lightbulb', [
                'chandeliers-spots' => ['نجف وسبوتات', 'lamp-ceiling'],
                'led-bulbs' => ['لمبات ليد', null],
                'led-profile-garden' => ['بروفايل ليد وإضاءة حدائق', null],
            ]],
            'industrial' => ['كهرباء صناعية', 'cog', [
                'rocket-sockets' => ['برايز صاروخ', null],
                'industrial-fans' => ['شفاط صناعي', 'fan'],
                'fuses' => ['فيوزات', null],
            ]],
            'smart-home' => ['سمارت هوم', 'wifi', [
                'motion-sensors' => ['حساسات حركة', null],
                'lighting-control' => ['تحكم إضاءة', null],
                'alarms' => ['إنذار', null],
            ]],
            'cameras-intercom' => ['كاميرات وانتركم', 'cctv', [
                'ip-cameras' => ['كاميرات IP', null],
                'intercom' => ['انتركم مرئي وصوتي', null],
            ]],
            'supplies' => ['مستلزمات', 'power-strip', [
                'transformers' => ['ترانسات', null],
                'power-strips' => ['مشتركات', null],
                'lamp-holders' => ['دوايات', null],
                'stabilizers' => ['استابليزر', 'battery-charging'],
            ]],
            'tools-equipment' => ['عدد ومعدات', 'drill', [
                'drills-grinders' => ['دريلات وصواريخ ومناشير', null],
                'cutting-discs' => ['أقراص تقطيع وتجليخ وسنفرة', null],
                'measuring-laser' => ['أجهزة قياس وليزر', 'gauge'],
            ]],
            'home-appliances' => ['أدوات منزلية', 'fan', [
                'heaters' => ['دفايات', 'heater'],
                'home-fans' => ['شفاطات', null],
                'mosquito-zappers' => ['صاعق ناموس', 'zap'],
            ]],
        ];

        $sort = 0;
        foreach ($tree as $slug => [$name, $icon, $children]) {
            $parent = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'icon' => $icon, 'parent_id' => null, 'is_active' => true, 'sort_order' => ++$sort]
            );
            $childSort = 0;
            foreach ($children as $childSlug => [$childName, $childIcon]) {
                Category::updateOrCreate(
                    ['slug' => $childSlug],
                    ['name' => $childName, 'icon' => $childIcon, 'parent_id' => $parent->id, 'is_active' => true, 'sort_order' => ++$childSort]
                );
            }
        }

        $this->seedAttributes();
    }

    private function seedAttributes(): void
    {
        $select = fn (array $pairs) => collect($pairs)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();

        $defs = [
            'breaker-panels' => [
                ['current_rating_a', 'شدة التيار', 'number', 'A', null],
                ['poles', 'عدد الأقطاب', 'select', null, $select(['1' => 'قطب واحد', '2' => 'قطبان', '3' => '٣ أقطاب', '4' => '٤ أقطاب'])],
                ['trip_curve', 'منحنى الفصل', 'select', null, $select(['B' => 'B', 'C' => 'C', 'D' => 'D'])],
                ['breaking_capacity_ka', 'قدرة القطع', 'number', 'kA', null],
            ],
            'wires-cables' => [
                ['cross_section_mm2', 'المقطع', 'number', 'mm²', null],
                ['cores', 'عدد القلوب', 'number', null, null],
                ['conductor_material', 'مادة الموصل', 'select', null, $select(['copper' => 'نحاس', 'aluminum' => 'ألومنيوم'])],
                ['insulation_type', 'نوع العزل', 'select', null, $select(['pvc' => 'PVC', 'xlpe' => 'XLPE'])],
            ],
            'led-bulbs' => [
                ['wattage_w', 'القدرة', 'number', 'W', null],
                ['lumen', 'شدة الإضاءة', 'number', 'lm', null],
                ['color_temperature_k', 'درجة حرارة اللون', 'number', 'K', null],
                ['base_type', 'نوع الدواية', 'select', null, $select(['e27' => 'E27', 'e14' => 'E14', 'gu10' => 'GU10'])],
                ['ip_rating', 'درجة الحماية', 'text', null, null],
            ],
            'drills-grinders' => [
                ['power_w', 'القدرة', 'number', 'W', null],
                ['voltage_v', 'الجهد', 'number', 'V', null],
                ['chuck_mm', 'مقاس الظرف', 'number', 'mm', null],
            ],
            'ip-cameras' => [
                ['resolution_mp', 'الدقة', 'number', 'MP', null],
                ['connectivity', 'الاتصال', 'select', null, $select(['wifi' => 'واي فاي', 'poe' => 'PoE', 'wired' => 'سلكي'])],
                ['night_vision', 'رؤية ليلية', 'boolean', null, null],
            ],
        ];

        foreach ($defs as $categorySlug => $attributes) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category === null) {
                continue;
            }
            foreach ($attributes as $i => [$key, $nameAr, $type, $unit, $options]) {
                CategoryAttribute::updateOrCreate(
                    ['category_id' => $category->id, 'key' => $key],
                    [
                        'name_ar' => $nameAr, 'data_type' => $type, 'unit_label' => $unit,
                        'options' => $options, 'is_filterable' => true, 'is_required' => false,
                        'is_active' => true, 'sort_order' => $i + 1,
                    ]
                );
            }
        }
    }
}
