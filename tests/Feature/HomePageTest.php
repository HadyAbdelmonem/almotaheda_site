<?php

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function homeBanner(string $title, BannerPosition $position, array $attrs = []): Banner
{
    return Banner::create(array_merge([
        'title' => $title, 'position' => $position, 'is_active' => true, 'sort_order' => 1,
    ], $attrs));
}

test('homepage shows hero, promo, categories, brands and product sections from the database', function () {
    homeBanner('عرض التأسيس الكبير', BannerPosition::Hero);
    homeBanner('خصم الأسبوع', BannerPosition::Promo);
    Category::factory()->create(['name' => 'إنارة وديكور', 'is_active' => true]);
    Brand::factory()->create(['name' => 'شنايدر', 'is_active' => true]);
    $best = Product::factory()->create(['name' => 'قاطع الأكثر مبيعًا', 'sales_count' => 99, 'is_active' => true]);
    Product::factory()->create(['name' => 'منتج عادي', 'sales_count' => 1, 'is_active' => true]);

    $response = $this->get('/')->assertOk()
        ->assertSee('عرض التأسيس الكبير')
        ->assertSee('خصم الأسبوع')
        ->assertSee('إنارة وديكور')
        ->assertSee('شنايدر')
        ->assertSee('قاطع الأكثر مبيعًا')
        ->assertSee('الأكثر مبيعاً')
        ->assertSee('وصل حديثاً');

    // Best sellers are ordered by sales_count (net count stored on the product).
    $content = $response->getContent();
    expect(strpos($content, 'قاطع الأكثر مبيعًا'))->toBeLessThan(strpos($content, 'منتج عادي'));
    expect($best->sales_count)->toBe('99.000');
});

test('homepage hides sections whose data is empty and invents nothing', function () {
    $response = $this->get('/')->assertOk();

    $response->assertDontSee('الأكثر مبيعاً')
        ->assertDontSee('وصل حديثاً')
        ->assertDontSee('تسوق حسب القسم')
        ->assertDontSee('عميل سعيد')
        ->assertDontSee('تقييمات العملاء');
});

test('hero respects the banner date window and active flag', function () {
    homeBanner('بانر مستقبلي', BannerPosition::Hero, ['starts_at' => now()->addDay()]);
    homeBanner('بانر منتهي', BannerPosition::Hero, ['ends_at' => now()->subDay()]);
    homeBanner('بانر موقوف', BannerPosition::Hero, ['is_active' => false]);
    homeBanner('بانر شغال', BannerPosition::Hero);

    $this->get('/')
        ->assertOk()
        ->assertSee('بانر شغال')
        ->assertDontSee('بانر مستقبلي')
        ->assertDontSee('بانر منتهي')
        ->assertDontSee('بانر موقوف');
});

test('why-us blocks come from settings and disappear when emptied', function () {
    Setting::updateOrCreate(['key' => 'why_us_blocks'], ['value' => [
        ['icon' => 'shield-check', 'title' => 'ضمان سنتين', 'text' => 'على كل المنتجات'],
    ]]);
    Cache::forget('setting.why_us_blocks');

    $this->get('/')->assertOk()->assertSee('ضمان سنتين')->assertSee('على كل المنتجات');

    Setting::updateOrCreate(['key' => 'why_us_blocks'], ['value' => []]);
    Cache::forget('setting.why_us_blocks');
    Cache::forget('home_sections');

    $this->get('/')->assertOk()->assertDontSee('ضمان سنتين');
});

test('inactive products never appear on the homepage', function () {
    Product::factory()->create(['name' => 'منتج موقوف', 'is_active' => false, 'sales_count' => 500]);

    $this->get('/')->assertOk()->assertDontSee('منتج موقوف');
});
