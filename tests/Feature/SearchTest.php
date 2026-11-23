<?php

use App\Livewire\LiveSearch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeSearchProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'model_code' => 'SP'.fake()->unique()->numberBetween(10000, 99999),
        'cost_price' => 44444,
    ], $attrs));
}

test('search finds products by exact model code and ranks it first', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'قاطع S201 إضافي', 'model_code' => 'XYZ201']);
    $exact = makeSearchProduct(['category_id' => $category->id, 'name' => 'قاطع كهرباء', 'model_code' => 'S201C16']);

    // The exact model-code query matches only the exact product.
    $html = $this->get('/search?q=S201C16')->assertOk()->getContent();
    expect($html)->toContain('قاطع كهرباء');
    expect($html)->not->toContain('قاطع S201 إضافي');
    expect($html)->not->toContain('44444');

    // In live search, the exact model-code match ranks above partial matches.
    $component = Livewire::test(LiveSearch::class)->set('query', 'S201');
    $liveHtml = $component->html();
    expect(strpos($liveHtml, 'قاطع S201 إضافي'))->toBeLessThan(strpos($liveHtml, 'قاطع كهرباء'));
});

test('search normalizes arabic spelling variants', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'لمبة إضاءة قوية', 'model_code' => 'LMP100']);

    // ة -> ه and أ/إ -> ا variants must all match.
    $this->get('/search?q='.urlencode('لمبه اضاءه'))->assertOk()->assertSee('لمبة إضاءة قوية');
    $this->get('/search?q='.urlencode('إضاءة'))->assertOk()->assertSee('لمبة إضاءة قوية');
});

test('search normalizes arabic-indic digits in model codes', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'مفتاح رقمي', 'model_code' => 'AB123']);

    // ١٢٣ are Arabic-Indic digits for 123.
    $this->get('/search?q='.urlencode('AB١٢٣'))->assertOk()->assertSee('مفتاح رقمي');
});

test('multiword search requires every token to match', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'دريل بوش قوي']);
    makeSearchProduct(['category_id' => $category->id, 'name' => 'دريل صيني']);

    $this->get('/search?q='.urlencode('دريل بوش'))
        ->assertOk()
        ->assertSee('دريل بوش قوي')
        ->assertDontSee('دريل صيني');
});

test('search matches brand names and variant skus', function () {
    $brand = Brand::factory()->create(['name' => 'شنايدر']);
    $category = Category::factory()->create();
    $byBrand = makeSearchProduct(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'قاطع مجهول']);
    $bySku = makeSearchProduct(['category_id' => $category->id, 'name' => 'مفتاح عادي']);
    ProductVariant::create([
        'product_id' => $bySku->id, 'name' => 'أبيض', 'sku' => 'SKU-UNIQUE-77',
        'stock_quantity' => 3, 'is_active' => true,
    ]);

    $this->get('/search?q='.urlencode('شنايدر'))->assertOk()->assertSee('قاطع مجهول');
    $this->get('/search?q=SKU-UNIQUE-77')->assertOk()->assertSee('مفتاح عادي');
});

test('search excludes inactive and soft deleted products', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'منتج ظاهر فريد']);
    makeSearchProduct(['category_id' => $category->id, 'name' => 'منتج موقوف فريد', 'is_active' => false]);
    $deleted = makeSearchProduct(['category_id' => $category->id, 'name' => 'منتج محذوف فريد']);
    $deleted->delete();

    $this->get('/search?q='.urlencode('فريد'))
        ->assertOk()
        ->assertSee('منتج ظاهر فريد')
        ->assertDontSee('منتج موقوف فريد')
        ->assertDontSee('منتج محذوف فريد');
});

test('search results page is noindex with canonical to base search url', function () {
    $this->get('/search?q='.urlencode('قاطع'))
        ->assertOk()
        ->assertSee('noindex', false)
        ->assertSee('rel="canonical"', false);
});

test('live search returns top results ranked with exact model first', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'قاطع S201 عادي', 'model_code' => 'AA100']);
    makeSearchProduct(['category_id' => $category->id, 'name' => 'قاطع أصلي', 'model_code' => 'S201C16']);

    Livewire::test(LiveSearch::class)
        ->set('query', 'S201C16')
        ->assertSee('قاطع أصلي')
        ->assertSee('عرض كل النتائج');
});

test('live search ignores very short queries', function () {
    $category = Category::factory()->create();
    makeSearchProduct(['category_id' => $category->id, 'name' => 'قاطع']);

    Livewire::test(LiveSearch::class)
        ->set('query', 'ق')
        ->assertDontSee('عرض كل النتائج');
});

test('search text is rebuilt when a variant sku changes and when a brand is renamed', function () {
    $brand = Brand::factory()->create(['name' => 'ماركة قديمة']);
    $category = Category::factory()->create();
    $product = makeSearchProduct(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'منتج ثابت']);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 'name' => 'أبيض', 'sku' => 'OLD-SKU-1',
        'stock_quantity' => 3, 'is_active' => true,
    ]);

    expect($product->fresh()->search_text)->toContain('old-sku-1');

    $variant->update(['sku' => 'NEW-SKU-9']);
    expect($product->fresh()->search_text)->toContain('new-sku-9');

    $brand->update(['name' => 'ماركة جديدة']);
    expect($product->fresh()->search_text)->toContain('ماركه جديده');
});
