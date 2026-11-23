<?php

namespace Database\Seeders;

use App\Enums\BannerPosition;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Support\DemoArt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * RichDemoSeeder — owner demo-data pack (branch: demo-data).
 *
 * Standalone: run explicitly with
 *   php artisan db:seed --class=RichDemoSeeder
 * It is NOT wired into DatabaseSeeder. Idempotent: products are
 * updateOrCreate'd by model_code, media collections are replaced
 * (never duplicated), reviews by (product, user), and demo orders are
 * only created when the orders table is completely empty.
 *
 * Every image is generated at seed time by App\Support\DemoArt (GD) —
 * no binary image files exist in the repository. All content is demo
 * placeholder data the owner replaces from the admin panel.
 */
class RichDemoSeeder extends Seeder
{
    /** @var array<string, Product> */
    private array $byModel = [];

    public function run(): void
    {
        $this->ensureBaseData();
        $this->seedProducts();
        $this->seedVariantsAndTiers();
        $this->attachProductImages();
        $this->seedCategoryImages();
        $this->seedBrandLogos();
        $this->seedBannerImages();
        $this->seedReviews();
        $this->seedOrders();

        Cache::forget('home_sections');
        Cache::forget('category_tree');

        $this->command?->info(sprintf(
            'RichDemoSeeder done: %d products (%d with gallery media), %d media files, %d reviews, %d orders, %d banners with images.',
            Product::count(),
            Product::has('media')->count(),
            Media::count(),
            Review::count(),
            Order::count(),
            Banner::whereNotNull('image_desktop')->count()
        ));
    }

    /** Call the base seeders only for the groups whose tables are empty. */
    private function ensureBaseData(): void
    {
        if (Setting::count() === 0) {
            $this->call(SettingSeeder::class);
        }
        if (ShippingRate::count() === 0) {
            $this->call(ShippingRateSeeder::class);
        }
        if (Category::count() === 0) {
            $this->call(CategorySeeder::class);
        }
        if (Brand::count() === 0) {
            $this->call(BrandSeeder::class);
        }
        if (Page::count() === 0) {
            $this->call(PageSeeder::class);
        }
        if (Banner::count() === 0) {
            $this->call(BannerSeeder::class);
        }
    }

    // ------------------------------------------------------------------
    // Products
    // ------------------------------------------------------------------

    private function seedProducts(): void
    {
        $brandIds = Brand::pluck('id', 'slug');
        $catIds = Category::pluck('id', 'slug');
        $featured = ['BR-RCCB40', 'LED-SMART', 'DR-650', 'CAM-P8MP', 'FAN-CEIL56', 'STAB-10K', 'SM-TOUCH2', 'LP-36M'];

        foreach ($this->catalog() as $i => $row) {
            [$model, $name, $brandSlug, $catSlug, $art, $variant, $price, $sale, $stock, $unit, $step, $sales, $attrs] = $row;
            $product = Product::updateOrCreate(
                ['model_code' => $model],
                [
                    'name' => $name,
                    'slug' => strtolower($model),
                    'brand_id' => $brandSlug !== null ? ($brandIds[$brandSlug] ?? null) : null,
                    'category_id' => $catIds[$catSlug] ?? null,
                    'short_description' => $this->shortDescription($name),
                    'description' => $this->description($name, $catSlug),
                    'specs' => $this->specsFor($art),
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
                    'is_featured' => in_array($model, $featured, true),
                    'sales_count' => $sales,
                ]
            );
            $this->byModel[$model] = $product;
            $this->seedAttributeValues($product, $attrs);
        }

        // Index the pre-existing demo products too (images/reviews/orders reuse them).
        foreach (Product::all() as $product) {
            $this->byModel[$product->model_code] ??= $product;
        }
    }

    /** Technical attribute values, same storage pattern as DemoProductSeeder. */
    private function seedAttributeValues(Product $product, array $attrs): void
    {
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

    private function shortDescription(string $name): string
    {
        return $name.' — جودة موثوقة بمواصفات قياسية، من شركة الصفا للتوريدات الكهربائية.';
    }

    private function description(string $name, string $catSlug): string
    {
        $phrases = [
            'breaker-panels' => 'حماية موثوقة للدوائر الكهربائية ضد زيادة التيار وقصر الدائرة، بفصل سريع وآمن.',
            'wire-supplies' => 'لوازم تأسيس أساسية لشغل نظيف وآمن داخل البواطات واللوحات.',
            'electrical-panels' => 'لوحة توزيع متينة بتجميع منظم للقواطع ومساحة مريحة للتمديدات.',
            'wires-cables' => 'موصلات عالية النقاء بعزل متين يتحمل ظروف التشغيل اليومية.',
            'switches-sockets' => 'خامات مقاومة للحريق ولمسة نهائية أنيقة تناسب كل التشطيبات.',
            'chandeliers-spots' => 'إضاءة موزعة بشكل مريح للعين وتضيف لمسة ديكور للمكان.',
            'led-bulbs' => 'إضاءة قوية باستهلاك منخفض للطاقة وعمر تشغيلي طويل.',
            'led-profile-garden' => 'حلول إضاءة مخفية وخارجية تضيف عمقًا وجمالًا للمساحات.',
            'rocket-sockets' => 'برايز صناعية شديدة التحمل للورش والمصانع والاستخدام الشاق.',
            'industrial-fans' => 'سحب هواء قوي بمحرك يتحمل التشغيل المتواصل.',
            'fuses' => 'حماية أساسية للدوائر والأجهزة ضد تجاوز التيار المسموح.',
            'motion-sensors' => 'استشعار دقيق للحركة لتشغيل الإضاءة تلقائيًا وتوفير الطاقة.',
            'lighting-control' => 'تحكم ذكي في الإضاءة من الموبايل أو باللمس، بتركيب بسيط.',
            'alarms' => 'تنبيه مبكر يحمي أسرتك وممتلكاتك على مدار الساعة.',
            'ip-cameras' => 'مراقبة واضحة ليلًا ونهارًا مع متابعة مباشرة من الموبايل.',
            'intercom' => 'تواصل واضح مع الزوار قبل فتح الباب، لمزيد من الأمان.',
            'transformers' => 'جهد ثابت وآمن لتشغيل الإضاءة والأجهزة منخفضة الجهد.',
            'power-strips' => 'توزيع آمن للطاقة مع حماية من زيادة التيار وطول سلك عملي.',
            'lamp-holders' => 'دوايات متينة تتحمل الحرارة وتناسب الاستخدام اليومي.',
            'stabilizers' => 'تثبيت للجهد يحمي أجهزتك من تقلبات التيار الكهربائي.',
            'drills-grinders' => 'قوة وأداء يعتمد عليه في أعمال التركيب والتشطيب والصيانة.',
            'cutting-discs' => 'قطع نظيف وسريع بعمر افتراضي طويل للقرص.',
            'measuring-laser' => 'قياسات دقيقة تختصر وقت الشغل وتقلل الأخطاء.',
            'heaters' => 'تدفئة سريعة وآمنة للغرف بمستويات حرارة قابلة للضبط.',
            'home-fans' => 'توزيع هواء مريح بصوت هادئ واستهلاك اقتصادي.',
            'mosquito-zappers' => 'تخلص من الناموس والحشرات الطائرة من غير مبيدات.',
        ];
        $phrase = $phrases[$catSlug] ?? 'منتج عملي بجودة يعتمد عليها في الاستخدام اليومي.';

        return $name.'. '.$phrase.' منتج تجريبي لأغراض العرض — المواصفات والسعر قابلان للتعديل من لوحة التحكم.';
    }

    /** Arabic spec sheet (products.specs JSON) per illustration type. */
    private function specsFor(string $art): array
    {
        return match ($art) {
            'breaker' => ['نوع الحماية' => 'حراري مغناطيسي', 'الجهد المقنن' => '230/400V', 'التركيب' => 'على سكة DIN', 'الضمان' => 'سنتان'],
            'distribution-board' => ['الخامة' => 'صاج مجلفن', 'درجة الحماية' => 'IP40', 'التركيب' => 'حائطي', 'الضمان' => 'سنة'],
            'switch', 'socket' => ['الخامة' => 'بولي كربونات مقاوم للحريق', 'الجهد المقنن' => '250V', 'الضمان' => '5 سنوات'],
            'cable-coil' => ['الموصل' => 'نحاس نقي', 'العزل' => 'PVC', 'الجهد المقنن' => '450/750V'],
            'led-bulb', 'led-panel' => ['العمر التشغيلي' => '25000 ساعة', 'معامل القدرة' => '0.9', 'الضمان' => 'سنتان'],
            'floodlight' => ['درجة الحماية' => 'IP66', 'الخامة' => 'ألومنيوم مصبوب', 'الضمان' => 'سنة'],
            'drill' => ['التغذية' => '220V', 'الضمان' => 'سنة'],
            'multimeter' => ['الشاشة' => 'LCD', 'الحماية' => 'ضد الحمل الزائد', 'الضمان' => 'سنة'],
            'ceiling-fan' => ['عدد السرعات' => '3', 'التركيب' => 'سقفي / حائطي', 'الضمان' => 'سنتان'],
            'stabilizer' => ['نطاق الإدخال' => '140-280V', 'الحماية' => 'ضد ارتفاع وانخفاض الجهد', 'الضمان' => 'سنتان'],
            'conduit' => ['الخامة' => 'PVC مقاوم للصدمات', 'وحدة البيع' => 'بالمتر'],
            'battery' => ['النوع' => 'حمضية مختومة', 'الضمان' => 'سنة'],
            default => ['الضمان' => 'سنة'],
        };
    }

    /**
     * The rich catalog: 79 additional products across ALL subcategories.
     * Row: [model, name, brand slug|null, category slug, art type, art variant,
     *       price, sale|null, stock, unit, step, sales_count, attributes]
     * Attributes: key => [type, value] using the §4.12 attribute system.
     */
    private function catalog(): array
    {
        $n = fn ($v) => ['number', $v];
        $t = fn ($v) => ['text', $v];
        $b = fn ($v) => ['boolean', $v];

        return [
            // ---- breaker-panels (لوح قواطع) ----
            ['BR-1P10', 'قاطع أحادي 10 أمبير منحنى C', 'abb', 'breaker-panels', 'breaker', 0, 150, null, 140, 'piece', 1, 210, ['current_rating_a' => $n(10), 'poles' => $t('1'), 'trip_curve' => $t('C'), 'breaking_capacity_ka' => $n(6)]],
            ['BR-1P25', 'قاطع أحادي 25 أمبير منحنى C', 'abb', 'breaker-panels', 'breaker', 1, 175, 155, 95, 'piece', 1, 160, ['current_rating_a' => $n(25), 'poles' => $t('1'), 'trip_curve' => $t('C'), 'breaking_capacity_ka' => $n(6)]],
            ['BR-2P20', 'قاطع ثنائي 20 أمبير منحنى C', 'schneider', 'breaker-panels', 'breaker', 1, 310, null, 70, 'piece', 1, 95, ['current_rating_a' => $n(20), 'poles' => $t('2'), 'trip_curve' => $t('C'), 'breaking_capacity_ka' => $n(6)]],
            ['BR-3P40', 'قاطع ثلاثي 40 أمبير منحنى C', 'schneider', 'breaker-panels', 'breaker', 2, 640, 575, 38, 'piece', 1, 60, ['current_rating_a' => $n(40), 'poles' => $t('3'), 'trip_curve' => $t('C'), 'breaking_capacity_ka' => $n(10)]],
            ['BR-RCCB40', 'قاطع تسرب أرضي 40 أمبير 30 مللي', 'abb', 'breaker-panels', 'breaker', 3, 890, null, 26, 'piece', 1, 45, ['current_rating_a' => $n(40), 'poles' => $t('2'), 'trip_curve' => $t('C'), 'breaking_capacity_ka' => $n(6)]],
            ['BR-4P100', 'قاطع رباعي 100 أمبير', 'schneider', 'breaker-panels', 'breaker', 3, 1480, null, 15, 'piece', 1, 22, ['current_rating_a' => $n(100), 'poles' => $t('4'), 'trip_curve' => $t('C'), 'breaking_capacity_ka' => $n(10)]],
            // ---- wire-supplies (لوازم أسلاك) ----
            ['WS-TAPE5', 'شريط لحام عازل PVC (عبوة 5)', null, 'wire-supplies', 'cable-coil', 2, 60, null, 400, 'pack', 1, 320, []],
            ['WS-BOX44', 'بواط معدن مجلفن 4×4', null, 'wire-supplies', 'switch', 3, 38, null, 260, 'piece', 1, 140, []],
            ['WS-LUGS100', 'كوس ترمل نحاس (عبوة 100)', null, 'wire-supplies', 'cable-coil', 3, 145, 125, 120, 'pack', 1, 88, []],
            ['WS-TIES100', 'أربطة كابلات نايلون 30 سم (عبوة 100)', null, 'wire-supplies', 'cable-coil', 0, 55, null, 300, 'pack', 1, 230, []],
            // ---- electrical-panels (لوحات كهربائية) ----
            ['LP-8M', 'لوحة قواطع 8 خط معدن', 'sanchi', 'electrical-panels', 'distribution-board', 0, 980, null, 32, 'piece', 1, 54, []],
            ['LP-18P', 'لوحة قواطع 18 خط بلاستيك', 'sanchi', 'electrical-panels', 'distribution-board', 2, 1650, 1490, 20, 'piece', 1, 41, []],
            ['LP-36M', 'لوحة قواطع 36 خط معدن', 'sanchi', 'electrical-panels', 'distribution-board', 3, 3400, null, 10, 'piece', 1, 18, []],
            // ---- wires-cables (أسلاك وكابلات ومواسير) ----
            ['CU-4-PVC', 'سلك نحاس معزول 4 مم — بالمتر', null, 'wires-cables', 'cable-coil', 2, 42, null, 720, 'meter', 0.25, 380, ['cross_section_mm2' => $n(4), 'cores' => $n(1), 'conductor_material' => $t('copper'), 'insulation_type' => $t('pvc')]],
            ['CU-6-PVC', 'سلك نحاس معزول 6 مم — بالمتر', null, 'wires-cables', 'cable-coil', 3, 63, 56, 540, 'meter', 0.25, 290, ['cross_section_mm2' => $n(6), 'cores' => $n(1), 'conductor_material' => $t('copper'), 'insulation_type' => $t('pvc')]],
            ['CU-2X15', 'كابل نحاس 2×1.5 مم — بالمتر', null, 'wires-cables', 'cable-coil', 0, 34, null, 610, 'meter', 0.5, 175, ['cross_section_mm2' => $n(1.5), 'cores' => $n(2), 'conductor_material' => $t('copper'), 'insulation_type' => $t('pvc')]],
            ['AL-16-XLPE', 'كابل ألومنيوم 16 مم XLPE — بالمتر', null, 'wires-cables', 'cable-coil', 1, 58, null, 430, 'meter', 0.5, 96, ['cross_section_mm2' => $n(16), 'cores' => $n(1), 'conductor_material' => $t('aluminum'), 'insulation_type' => $t('xlpe')]],
            ['PIPE-20', 'مواسير كهرباء 20 مم — بالمتر', null, 'wires-cables', 'conduit', 1, 16, null, 800, 'meter', 1, 210, []],
            // ---- switches-sockets (مفاتيح وبرايز) ----
            ['SW-2G', 'مفتاح إضاءة مزدوج', 'mk', 'switches-sockets', 'switch', 1, 125, null, 180, 'piece', 1, 150, []],
            ['SK-1G', 'بريزة أحادية 16 أمبير', 'mk', 'switches-sockets', 'socket', 0, 85, 72, 240, 'piece', 1, 265, []],
            ['SW-DIM', 'مفتاح ديمر إضاءة 500 وات', 'osa', 'switches-sockets', 'switch', 2, 240, null, 65, 'piece', 1, 58, []],
            ['SK-3G', 'بريزة ثلاثية مؤرضة', 'mk', 'switches-sockets', 'socket', 1, 195, null, 110, 'piece', 1, 132, []],
            ['SW-BELL', 'مفتاح جرس باب', 'osa', 'switches-sockets', 'switch', 0, 70, null, 150, 'piece', 1, 74, []],
            // ---- chandeliers-spots (نجف وسبوتات) ----
            ['SPOT-12W', 'سبوت ليد 12 وات دائري', null, 'chandeliers-spots', 'led-panel', 0, 165, 139, 210, 'piece', 1, 188, []],
            ['SPOT-GU10', 'سبوت GU10 قابل للتوجيه', null, 'chandeliers-spots', 'led-panel', 1, 95, null, 175, 'piece', 1, 96, []],
            ['CH-LED48', 'نجفة ليد حديثة 48 وات', null, 'chandeliers-spots', 'led-panel', 3, 2450, null, 0, 'piece', 1, 27, []], // out of stock
            ['COB-STRIP', 'شريط إضاءة COB مخفي — بالمتر', null, 'chandeliers-spots', 'cable-coil', 1, 120, null, 300, 'meter', 0.5, 143, []],
            // ---- led-bulbs (لمبات ليد) ----
            ['LED-5W-E14', 'لمبة ليد 5 وات E14', 'singer', 'led-bulbs', 'led-bulb', 0, 45, null, 420, 'piece', 1, 340, ['wattage_w' => $n(5), 'lumen' => $n(400), 'color_temperature_k' => $n(3000), 'base_type' => $t('e14')]],
            ['LED-15W', 'لمبة ليد 15 وات E27', 'singer', 'led-bulbs', 'led-bulb', 2, 105, 89, 300, 'piece', 1, 275, ['wattage_w' => $n(15), 'lumen' => $n(1400), 'color_temperature_k' => $n(6500), 'base_type' => $t('e27')]],
            ['LED-20W', 'لمبة ليد 20 وات E27', 'singer', 'led-bulbs', 'led-bulb', 3, 135, null, 260, 'piece', 1, 198, ['wattage_w' => $n(20), 'lumen' => $n(1900), 'color_temperature_k' => $n(6500), 'base_type' => $t('e27')]],
            ['LED-SMART', 'لمبة ليد ذكية واي فاي ملونة', null, 'led-bulbs', 'led-bulb', 1, 290, null, 85, 'piece', 1, 117, ['wattage_w' => $n(12), 'lumen' => $n(1050), 'color_temperature_k' => $n(6500), 'base_type' => $t('e27')]],
            ['LED-G95', 'لمبة كروية ديكور G95', null, 'led-bulbs', 'led-bulb', 0, 150, 129, 95, 'piece', 1, 66, ['wattage_w' => $n(8), 'lumen' => $n(750), 'color_temperature_k' => $n(2700), 'base_type' => $t('e27')]],
            // ---- led-profile-garden (بروفايل ليد وإضاءة حدائق) ----
            ['GD-SOLAR', 'كشاف حديقة يعمل بالطاقة الشمسية', null, 'led-profile-garden', 'floodlight', 0, 420, null, 4, 'piece', 1, 83, []], // low stock
            ['LED-STRIP5M', 'شريط ليد 5 متر 12 فولت', null, 'led-profile-garden', 'cable-coil', 2, 210, 179, 130, 'roll', 1, 154, []],
            ['PROF-ANG', 'بروفايل ليد زاوية — بالمتر', null, 'led-profile-garden', 'led-panel', 2, 110, null, 190, 'meter', 0.5, 71, []],
            // ---- rocket-sockets (برايز صاروخ) ----
            ['IND-SK16', 'بريزة صاروخ 16 أمبير صناعية', 'sanchi', 'rocket-sockets', 'socket', 3, 175, null, 90, 'piece', 1, 64, []],
            ['IND-PL32', 'قابس صاروخ 32 أمبير', 'sanchi', 'rocket-sockets', 'socket', 2, 150, 132, 85, 'piece', 1, 57, []],
            ['IND-SK63', 'بريزة صاروخ ثلاثية 63 أمبير', 'sanchi', 'rocket-sockets', 'socket', 1, 480, null, 30, 'piece', 1, 25, []],
            // ---- industrial-fans (شفاط صناعي) ----
            ['FAN-IND10', 'شفاط صناعي 10 بوصة', 'singer', 'industrial-fans', 'ceiling-fan', 2, 890, null, 22, 'piece', 1, 36, []],
            ['FAN-IND16', 'شفاط صناعي 16 بوصة', 'singer', 'industrial-fans', 'ceiling-fan', 3, 1590, 1420, 12, 'piece', 1, 19, []],
            // ---- fuses (فيوزات) ----
            ['FUSE-16A-10', 'فيوز سيراميك 16 أمبير (عبوة 10)', null, 'fuses', 'conduit', 0, 52, null, 280, 'pack', 1, 145, []],
            ['FUSE-25A-10', 'فيوز سيراميك 25 أمبير (عبوة 10)', null, 'fuses', 'conduit', 2, 58, null, 240, 'pack', 1, 118, []],
            ['FUSE-BASE', 'قاعدة فيوز سيراميك 32 أمبير', null, 'fuses', 'conduit', 3, 35, null, 190, 'piece', 1, 92, []],
            // ---- motion-sensors (حساسات حركة) ----
            ['MS-WALL', 'حساس حركة جداري 180 درجة', null, 'motion-sensors', 'switch', 1, 260, 225, 75, 'piece', 1, 88, []],
            ['MS-MINI', 'حساس وجود سقفي ميني', null, 'motion-sensors', 'switch', 3, 310, null, 48, 'piece', 1, 39, []],
            // ---- lighting-control (تحكم إضاءة) ----
            ['SM-TOUCH2', 'مفتاح لمس ذكي مزدوج واي فاي', null, 'lighting-control', 'switch', 2, 620, null, 42, 'piece', 1, 51, []],
            ['SM-CURT', 'وحدة تحكم ستائر ذكية', null, 'lighting-control', 'switch', 0, 780, 690, 25, 'piece', 1, 22, []],
            // ---- alarms (إنذار) ----
            ['AL-SMOKE', 'كاشف دخان ضوئي ببطارية', null, 'alarms', 'switch', 1, 340, null, 80, 'piece', 1, 63, []],
            ['AL-GAS', 'كاشف تسرب غاز طبيعي', null, 'alarms', 'socket', 0, 520, null, 45, 'piece', 1, 34, []],
            ['AL-BELL', 'جرس باب لاسلكي بمدى 150 متر', null, 'alarms', 'switch', 3, 285, 249, 70, 'piece', 1, 77, []],
            // ---- ip-cameras (كاميرات IP) ----
            ['CAM-IN2MP', 'كاميرا IP داخلية 2 ميجا واي فاي', null, 'ip-cameras', 'floodlight', 1, 890, null, 48, 'piece', 1, 71, ['resolution_mp' => $n(2), 'connectivity' => $t('wifi'), 'night_vision' => $b(true)]],
            ['CAM-P8MP', 'كاميرا IP خارجية 8 ميجا PoE', null, 'ip-cameras', 'floodlight', 2, 3350, null, 16, 'piece', 1, 23, ['resolution_mp' => $n(8), 'connectivity' => $t('poe'), 'night_vision' => $b(true)]],
            // ---- intercom (انتركم) ----
            ['INT-A4', 'انتركم صوتي 4 خط', null, 'intercom', 'switch', 0, 1650, null, 18, 'piece', 1, 15, []],
            // ---- transformers (ترانسات) ----
            ['TR-12V5A', 'ترانس 12 فولت 5 أمبير', null, 'transformers', 'stabilizer', 0, 320, null, 95, 'piece', 1, 84, []],
            ['TR-24V10A', 'ترانس 24 فولت 10 أمبير', null, 'transformers', 'stabilizer', 1, 540, 479, 60, 'piece', 1, 47, []],
            ['TR-220110', 'محول جهد 220 إلى 110 فولت 1000 وات', null, 'transformers', 'stabilizer', 2, 890, null, 28, 'piece', 1, 29, []],
            // ---- power-strips (مشتركات) ----
            ['PS-3', 'مشترك 3 عين بسلك 2 متر', 'osa', 'power-strips', 'socket', 0, 120, null, 160, 'piece', 1, 143, []],
            ['PS-6USB', 'مشترك 6 عين مع منافذ USB', 'osa', 'power-strips', 'socket', 2, 340, 299, 75, 'piece', 1, 96, []],
            ['PS-TOWER', 'مشترك برج 8 عين بحماية', 'osa', 'power-strips', 'socket', 1, 460, null, 40, 'piece', 1, 38, []],
            // ---- lamp-holders (دوايات) ----
            ['LH-E27C', 'دواية E27 سيراميك', null, 'lamp-holders', 'led-bulb', 1, 28, null, 500, 'piece', 1, 388, []],
            ['LH-E14', 'دواية E14 بلاستيك حراري', null, 'lamp-holders', 'led-bulb', 0, 18, null, 450, 'piece', 1, 240, []],
            ['LH-PEND', 'دواية تعليق بسلك', null, 'lamp-holders', 'led-bulb', 2, 45, 38, 220, 'piece', 1, 129, []],
            // ---- stabilizers (استابليزر) ----
            ['STAB-3K', 'استابليزر 3 كيلو', 'sanchi', 'stabilizers', 'stabilizer', 0, 3400, null, 12, 'piece', 1, 21, []],
            ['STAB-10K', 'استابليزر 10 كيلو', 'sanchi', 'stabilizers', 'stabilizer', 2, 8900, 8250, 3, 'piece', 1, 11, []], // low stock
            // ---- drills-grinders (دريلات وصواريخ ومناشير) ----
            ['DR-650', 'شنيور تخريم 650 وات', 'bosch', 'drills-grinders', 'drill', 0, 1950, null, 32, 'piece', 1, 88, ['power_w' => $n(650), 'voltage_v' => $n(220), 'chuck_mm' => $n(10)]],
            ['AG-9IN', 'صاروخ تجليخ 9 بوصة 2000 وات', 'bosch', 'drills-grinders', 'drill', 2, 2750, 2449, 20, 'piece', 1, 44, ['power_w' => $n(2000), 'voltage_v' => $n(220)]],
            ['SAW-185', 'منشار دائري 185 مم 1400 وات', 'bosch', 'drills-grinders', 'drill', 3, 3600, null, 14, 'piece', 1, 26, ['power_w' => $n(1400), 'voltage_v' => $n(220)]],
            ['CD-12V', 'دريل بطارية 12 فولت مع بطاريتين', 'bosch', 'drills-grinders', 'drill', 1, 2350, 2099, 24, 'piece', 1, 67, ['voltage_v' => $n(12), 'chuck_mm' => $n(10)]],
            // ---- cutting-discs (أقراص تقطيع) ----
            ['DISC-G10', 'أقراص تجليخ 4.5 بوصة (عبوة 10)', 'bosch', 'cutting-discs', 'cable-coil', 0, 135, null, 380, 'pack', 1, 176, []],
            ['DISC-S10', 'أقراص سنفرة (عبوة 10)', 'bosch', 'cutting-discs', 'cable-coil', 3, 95, 79, 340, 'pack', 1, 141, []],
            // ---- measuring-laser (أجهزة قياس وليزر) ----
            ['MM-CLAMP', 'أفوميتر كلامب رقمي', null, 'measuring-laser', 'multimeter', 1, 620, null, 55, 'piece', 1, 73, []],
            ['LASER-40', 'ميزان ليزر أخضر 40 متر', 'bosch', 'measuring-laser', 'multimeter', 2, 1890, 1690, 22, 'piece', 1, 35, []],
            ['DET-WALL', 'كاشف أسلاك ومعادن جداري', 'bosch', 'measuring-laser', 'multimeter', 3, 740, null, 30, 'piece', 1, 28, []],
            // ---- heaters (دفايات) ----
            ['HT-OIL11', 'دفاية زيت 11 ريشة 2500 وات', 'singer', 'heaters', 'floodlight', 1, 2950, null, 16, 'piece', 1, 31, []],
            ['HT-CER', 'دفاية سيراميك بريموت 2000 وات', 'singer', 'heaters', 'floodlight', 2, 1350, 1199, 20, 'piece', 1, 42, []],
            // ---- home-fans (شفاطات / مراوح منزلية) ----
            ['FAN-CEIL56', 'مروحة سقف 56 بوصة 3 ريشة', 'singer', 'home-fans', 'ceiling-fan', 0, 1650, null, 26, 'piece', 1, 58, []],
            ['FAN-WALL16', 'مروحة حائط 16 بوصة بريموت', 'singer', 'home-fans', 'ceiling-fan', 1, 1150, 1020, 30, 'piece', 1, 47, []],
            ['FAN-BATH20', 'شفاط حمام 20 سم', 'singer', 'home-fans', 'ceiling-fan', 2, 390, null, 65, 'piece', 1, 81, []],
            // ---- mosquito-zappers (صاعق ناموس) ----
            ['MZ-30W', 'صاعق ناموس كهربائي 30 وات', null, 'mosquito-zappers', 'floodlight', 3, 460, null, 42, 'piece', 1, 53, []],
        ];
    }

    // ------------------------------------------------------------------
    // Variants & price tiers (12 variant products, 5 tiered products)
    // ------------------------------------------------------------------

    private function seedVariantsAndTiers(): void
    {
        $colors = ['اللون', [['أبيض', 'W', 0], ['أسود', 'B', 0], ['ذهبي', 'G', 15]]];
        $variantDefs = [
            'SW-2G' => $colors,
            'SK-1G' => $colors,
            'SK-3G' => $colors,
            'SW-DIM' => ['اللون', [['أبيض', 'W', 0], ['أسود', 'B', 0]]],
            'PS-6USB' => ['اللون', [['أبيض', 'W', 0], ['أسود', 'B', 0]]],
            'HT-CER' => ['اللون', [['أبيض', 'W', 0], ['أسود', 'B', 0]]],
            'SM-TOUCH2' => $colors,
            'FAN-CEIL56' => ['اللون', [['أبيض', 'W', 0], ['بني', 'BR', 0], ['أسود', 'B', 0]]],
            'LED-15W' => ['درجة اللون', [['أبيض بارد 6500K', 'CW', 0], ['دافئ 3000K', 'WW', 0]]],
            'LED-5W-E14' => ['درجة اللون', [['أبيض بارد 6500K', 'CW', 0], ['دافئ 3000K', 'WW', 0]]],
            'DR-650' => ['الطقم', [['أساسي', 'STD', 0], ['مع شنطة وملحقات', 'KIT', 350]]],
            'CAM-IN2MP' => ['الذاكرة', [['بدون كارت', 'NM', 0], ['مع كارت 64GB', '64G', 180]]],
        ];

        foreach ($variantDefs as $model => [$optionKey, $options]) {
            $product = $this->byModel[$model] ?? null;
            if ($product === null) {
                continue;
            }
            $product->update(['has_variants' => true, 'stock_quantity' => 0]);
            foreach ($options as $sort => [$name, $suffix, $delta]) {
                $product->variants()->updateOrCreate(
                    ['sku' => $model.'-'.$suffix],
                    [
                        'name' => $name,
                        'options' => [$optionKey => $name],
                        'price' => $delta > 0 ? (float) $product->price + $delta : null,
                        'stock_quantity' => 14 + $sort * 6,
                        'is_active' => true,
                        'sort_order' => $sort,
                    ]
                );
            }
        }

        $tierDefs = [
            'CU-4-PVC' => [[50, 5], [100, 10]],
            'CU-6-PVC' => [[50, 5], [100, 12]],
            'LED-15W' => [[10, 5], [25, 10]],
            'DISC-G10' => [[5, 5], [20, 12]],
            'WS-TAPE5' => [[10, 5], [30, 10]],
        ];
        foreach ($tierDefs as $model => $tiers) {
            $product = $this->byModel[$model] ?? null;
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

    // ------------------------------------------------------------------
    // Generated media
    // ------------------------------------------------------------------

    /** Art type + variant for the pre-existing DemoProductSeeder products. */
    private function demoArtMap(): array
    {
        return [
            'S201C16' => ['breaker', 1], 'S202C32' => ['breaker', 2], 'S203C63' => ['breaker', 3],
            'LP-12M' => ['distribution-board', 1], 'LP-24M' => ['distribution-board', 3],
            'CU-1.5-PVC' => ['cable-coil', 0], 'CU-2.5-PVC' => ['cable-coil', 1],
            'CU-3X6' => ['cable-coil', 2], 'ROLL-2.5-100' => ['cable-coil', 3], 'PIPE-16' => ['conduit', 0],
            'SW-1G' => ['switch', 0], 'SK-2G' => ['socket', 0], 'SW-HT32' => ['switch', 1], 'SK-USB2' => ['socket', 2],
            'LED-9W-E27' => ['led-bulb', 1], 'LED-12W-E27' => ['led-bulb', 2],
            'SPOT-7W' => ['led-panel', 1], 'PROF-LED-2M' => ['led-panel', 2],
            'IND-SK32' => ['socket', 3], 'FAN-IND12' => ['ceiling-fan', 2], 'FUSE-10A-10' => ['conduit', 1],
            'MS-360' => ['switch', 2], 'SM-SW4' => ['switch', 3],
            'CAM-W3MP' => ['floodlight', 1], 'CAM-P5MP' => ['floodlight', 2], 'INT-V7' => ['switch', 0],
            'PS-4P' => ['socket', 1], 'STAB-5K' => ['stabilizer', 1],
            'DR-800H' => ['drill', 1], 'AG-720' => ['drill', 0], 'DISC-C10' => ['cable-coil', 0],
            'MM-DT830' => ['multimeter', 0], 'HT-4H' => ['floodlight', 3],
            'FAN-K25' => ['ceiling-fan', 1], 'MZ-20W' => ['floodlight', 0],
        ];
    }

    private function attachProductImages(): void
    {
        $artByModel = $this->demoArtMap();
        foreach ($this->catalog() as $row) {
            $artByModel[$row[0]] = [$row[4], $row[5]];
        }

        foreach ($artByModel as $model => [$type, $variant]) {
            $product = $this->byModel[$model] ?? null;
            if ($product === null) {
                continue;
            }
            // Replace, never duplicate, on re-runs.
            $product->clearMediaCollection('gallery');
            foreach ([0, 1] as $shot) {
                $path = DemoArt::product($type, $variant + $shot);
                $this->attach($product, 'gallery', $path, $product->name);
            }
        }
    }

    private function seedCategoryImages(): void
    {
        $map = [
            'foundation' => ['breaker', 2],
            'finishing' => ['switch', 0],
            'lighting-decor' => ['led-bulb', 1],
            'industrial' => ['socket', 3],
            'smart-home' => ['switch', 2],
            'cameras-intercom' => ['floodlight', 1],
            'supplies' => ['stabilizer', 1],
            'tools-equipment' => ['drill', 1],
            'home-appliances' => ['ceiling-fan', 0],
        ];
        foreach ($map as $slug => [$type, $variant]) {
            $category = Category::where('slug', $slug)->first();
            if ($category === null) {
                continue;
            }
            $path = DemoArt::category($type, $variant);
            $this->attach($category, 'image', $path, $category->name);
        }
    }

    private function seedBrandLogos(): void
    {
        $initials = [
            'abb' => 'ABB', 'schneider' => 'SE', 'bosch' => 'BOSCH',
            'sanchi' => 'SAN', 'osa' => 'OSA', 'singer' => 'SINGER', 'mk' => 'MK',
        ];
        foreach (Brand::all() as $i => $brand) {
            $text = $initials[$brand->slug] ?? strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $brand->slug) ?: 'AL', 0, 3));
            $path = DemoArt::brandLogo($text, $i);
            $this->attach($brand, 'logo', $path, $brand->name);
        }
    }

    /** Banner images through the same public-disk path the Filament upload uses. */
    private function seedBannerImages(): void
    {
        $heroTypes = ['breaker', 'drill', 'led-bulb'];
        $promoTypes = ['socket', 'stabilizer', 'multimeter'];

        foreach ([BannerPosition::Hero, BannerPosition::Promo] as $position) {
            $types = $position === BannerPosition::Hero ? $heroTypes : $promoTypes;
            $banners = Banner::where('position', $position)->orderBy('sort_order')->get();
            foreach ($banners as $i => $banner) {
                $type = $types[$i % count($types)];
                $generated = DemoArt::banner($type, $i);
                $stored = 'banners/demo-'.$position->value.'-'.($i + 1).'.webp';
                Storage::disk('public')->put($stored, file_get_contents($generated));
                @unlink($generated);
                $banner->update(['image_desktop' => $stored, 'image_mobile' => $stored]);
            }
        }
    }

    /** Attach a generated file; the media library consumes the temp file. */
    private function attach($model, string $collection, string $path, string $name): void
    {
        try {
            $model->addMedia($path)->usingName($name)->toMediaCollection($collection);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    // ------------------------------------------------------------------
    // Demo customers, reviews, orders
    // ------------------------------------------------------------------

    /** @return array<int, User> */
    private function demoCustomers(): array
    {
        $names = [
            'أحمد محمود', 'محمد السيد', 'خالد إبراهيم', 'محمود حسن', 'عمر فاروق', 'يوسف علي',
            'كريم سامي', 'طارق نبيل', 'حسام رضا', 'مصطفى كامل', 'إسلام عادل', 'هاني شوقي',
        ];
        $users = [];
        foreach ($names as $i => $name) {
            $users[] = User::updateOrCreate(
                ['email' => 'demo.customer'.($i + 1).'@example.com'],
                [
                    'name' => $name,
                    'phone' => '010'.str_pad((string) (12345678 + $i * 137913), 8, '0', STR_PAD_LEFT),
                    'password' => 'password',
                ]
            );
        }

        return $users;
    }

    private function seedReviews(): void
    {
        $users = $this->demoCustomers();
        $comments = [
            'منتج ممتاز والخامة نضيفة، شغال معايا بقاله شهرين من غير مشاكل.',
            'جودة كويسة جدًا مقابل السعر، والتوصيل وصل في يومين.',
            'جربته في شغل تأسيس شقة كاملة وكان أداؤه ثابت.',
            'الخامة أحسن من اللي كنت بشتريه من السوق، أنصح بيه.',
            'كويس بس كنت أتمنى التغليف يكون أقوى، المنتج نفسه تمام.',
            'شغال زي الفل، وركبته بنفسي من غير فني.',
            'سعر مناسب وجودة محترمة، هطلب تاني أكيد.',
            'مطابق للوصف تمامًا، والتعامل راقي.',
            'أداء ثابت ومفيش سخونية ولا صوت، تجربة شراء موفقة.',
            'منتج أصلي فعلًا، فرق واضح عن التقليد اللي جربته قبل كده.',
        ];
        $ratingPattern = [5, 4, 5, 3, 5, 4];

        $products = Product::where('is_active', true)->orderByDesc('sales_count')->take(30)->get();
        foreach ($products as $product) {
            $count = 2 + ($product->id % 4); // 2..5 reviews
            for ($k = 0; $k < $count; $k++) {
                $user = $users[($product->id + $k) % count($users)];
                Review::updateOrCreate(
                    ['product_id' => $product->id, 'user_id' => $user->id],
                    [
                        'rating' => $ratingPattern[($product->id + $k) % count($ratingPattern)],
                        'comment' => $comments[($product->id * 3 + $k) % count($comments)],
                        'status' => ReviewStatus::Approved,
                        'is_verified_purchase' => $k % 3 !== 2,
                        'created_at' => now()->subDays((($product->id * 7 + $k * 3) % 40) + 1),
                    ]
                );
            }
        }
    }

    /** Demo orders only on a store with no orders at all yet. */
    private function seedOrders(): void
    {
        if (Order::count() > 0) {
            return;
        }
        $users = $this->demoCustomers();
        $products = Product::where('is_active', true)->where('has_variants', false)
            ->orderByDesc('sales_count')->take(24)->get();
        if ($products->isEmpty()) {
            return;
        }

        $statuses = [
            OrderStatus::New, OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Processing,
            OrderStatus::Shipped, OrderStatus::Shipped, OrderStatus::Delivered,
            OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Cancelled,
        ];
        $places = [
            ['القاهرة', 'مدينة نصر', 50], ['الجيزة', 'المهندسين', 50], ['الإسكندرية', 'سموحة', 60],
            ['القاهرة', 'التجمع الخامس', 50], ['المنوفية', 'شبين الكوم', 65], ['أسيوط', 'أسيوط المدينة', 75],
        ];
        $unitLabels = config('units');

        foreach ($statuses as $i => $status) {
            $user = $users[$i % count($users)];
            [$gov, $city, $fee] = $places[$i % count($places)];
            $placedAt = now()->subDays(18 - $i * 2)->setTime(11 + ($i % 6), 15);

            $order = Order::create([
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'governorate' => $gov,
                'city' => $city,
                'address' => (12 + $i * 3).' شارع رئيسي، بجوار مسجد النور',
                'payment_method' => PaymentMethod::Cod,
                'payment_status' => $status === OrderStatus::Delivered ? PaymentStatus::Paid : PaymentStatus::Pending,
                'status' => $status,
                'shipping_fee' => $fee,
                'placed_at' => $placedAt,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            $subtotal = 0.0;
            $itemCount = 1 + ($i % 3);
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[($i * 3 + $j * 5) % $products->count()];
                $qty = $product->unit_code === 'meter' ? 10 : 1 + (($i + $j) % 3);
                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->model_code,
                    'unit_code' => $product->unit_code,
                    'unit_label_ar' => $unitLabels[$product->unit_code]['label_ar'] ?? 'قطعة',
                    'unit_price' => $unitPrice,
                    'cost_price' => (float) $product->cost_price,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'total' => $subtotal + $fee,
            ]);
            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => $status->value,
                'note' => 'طلب تجريبي — بيانات عرض فقط',
                'created_at' => $placedAt,
            ]);
        }
    }
}
