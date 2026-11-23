<?php

use App\Livewire\ListingPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeListingProduct(Category $category, array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'category_id' => $category->id,
        'model_code' => 'LP'.fake()->unique()->numberBetween(10000, 99999),
        'cost_price' => 55555,
    ], $attrs));
}

test('parent category listing includes products of its children', function () {
    $parent = Category::factory()->create(['name' => 'تأسيس كهرباء']);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'أسلاك وكابلات']);
    $other = Category::factory()->create();

    makeListingProduct($parent, ['name' => 'منتج الأب']);
    makeListingProduct($child, ['name' => 'منتج الابن']);
    makeListingProduct($other, ['name' => 'منتج غريب']);

    $this->get('/categories/'.$parent->slug)
        ->assertOk()
        ->assertSee('منتج الأب')
        ->assertSee('منتج الابن')
        ->assertDontSee('منتج غريب')
        ->assertDontSee('55555');
});

test('brand listing shows only that brand products', function () {
    $brand = Brand::factory()->create(['name' => 'شنايدر']);
    $category = Category::factory()->create();
    makeListingProduct($category, ['brand_id' => $brand->id, 'name' => 'منتج شنايدر']);
    makeListingProduct($category, ['name' => 'منتج ماركة أخرى']);

    $this->get('/brands/'.$brand->slug)
        ->assertOk()
        ->assertSee('منتج شنايدر')
        ->assertDontSee('منتج ماركة أخرى');
});

test('technical attribute filters combine with brand and stock filters', function () {
    $category = Category::factory()->create();
    $rating = CategoryAttribute::create([
        'category_id' => $category->id, 'key' => 'current_rating_a', 'name_ar' => 'التيار',
        'data_type' => 'number', 'unit_label' => 'A', 'is_filterable' => true, 'is_active' => true,
    ]);
    $poles = CategoryAttribute::create([
        'category_id' => $category->id, 'key' => 'poles', 'name_ar' => 'الأقطاب',
        'data_type' => 'select', 'options' => [['value' => '2', 'label' => 'ثنائي'], ['value' => '3', 'label' => 'ثلاثي']],
        'is_filterable' => true, 'is_active' => true,
    ]);
    $brandA = Brand::factory()->create();
    $brandB = Brand::factory()->create();

    $match = makeListingProduct($category, ['brand_id' => $brandA->id, 'name' => 'قاطع مطابق', 'stock_quantity' => 5]);
    ProductAttributeValue::create(['product_id' => $match->id, 'category_attribute_id' => $rating->id, 'value_number' => 16]);
    ProductAttributeValue::create(['product_id' => $match->id, 'category_attribute_id' => $poles->id, 'value_text' => '2', 'value_label_snapshot' => 'ثنائي']);

    $wrongRating = makeListingProduct($category, ['brand_id' => $brandA->id, 'name' => 'قاطع تيار مختلف', 'stock_quantity' => 5]);
    ProductAttributeValue::create(['product_id' => $wrongRating->id, 'category_attribute_id' => $rating->id, 'value_number' => 32]);
    ProductAttributeValue::create(['product_id' => $wrongRating->id, 'category_attribute_id' => $poles->id, 'value_text' => '2', 'value_label_snapshot' => 'ثنائي']);

    $outOfStock = makeListingProduct($category, ['brand_id' => $brandA->id, 'name' => 'قاطع نافد', 'stock_quantity' => 0]);
    ProductAttributeValue::create(['product_id' => $outOfStock->id, 'category_attribute_id' => $rating->id, 'value_number' => 16]);
    ProductAttributeValue::create(['product_id' => $outOfStock->id, 'category_attribute_id' => $poles->id, 'value_text' => '2', 'value_label_snapshot' => 'ثنائي']);

    Livewire::test(ListingPage::class, ['slug' => $category->slug, 'mode' => 'category'])
        ->set('attr', ['current_rating_a' => '16', 'poles' => '2'])
        ->assertSee('قاطع مطابق')
        ->assertDontSee('قاطع تيار مختلف')
        ->set('inStock', true)
        ->assertSee('قاطع مطابق')
        ->assertDontSee('قاطع نافد');
});

test('inherited parent attributes are filterable on the child category', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $attribute = CategoryAttribute::create([
        'category_id' => $parent->id, 'key' => 'trip_curve', 'name_ar' => 'منحنى الفصل',
        'data_type' => 'select', 'options' => [['value' => 'C', 'label' => 'C'], ['value' => 'B', 'label' => 'B']],
        'is_filterable' => true, 'is_active' => true,
    ]);
    $product = makeListingProduct($child, ['name' => 'قاطع ابن']);
    ProductAttributeValue::create(['product_id' => $product->id, 'category_attribute_id' => $attribute->id, 'value_text' => 'C', 'value_label_snapshot' => 'C']);

    Livewire::test(ListingPage::class, ['slug' => $child->slug, 'mode' => 'category'])
        ->assertSee('منحنى الفصل')
        ->set('attr', ['trip_curve' => 'B'])
        ->assertDontSee('قاطع ابن')
        ->set('attr', ['trip_curve' => 'C'])
        ->assertSee('قاطع ابن');
});

test('invalid attribute filter keys and values are ignored server-side', function () {
    $category = Category::factory()->create();
    makeListingProduct($category, ['name' => 'منتج وحيد']);

    Livewire::test(ListingPage::class, ['slug' => $category->slug, 'mode' => 'category'])
        ->set('attr', ['not_a_real_key' => 'x', 'price' => '0'])
        ->assertSee('منتج وحيد')
        ->assertSet('attr', []);
});

test('filter state lives in the url query string and restores on load', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    makeListingProduct($category, ['brand_id' => $brand->id, 'name' => 'منتج الماركة', 'price' => 100]);
    makeListingProduct($category, ['name' => 'منتج تاني', 'price' => 900]);

    $this->get('/categories/'.$category->slug.'?brands[]='.$brand->id.'&sort=price_asc')
        ->assertOk()
        ->assertSee('منتج الماركة')
        ->assertDontSee('منتج تاني');

    $component = Livewire::test(ListingPage::class, ['slug' => $category->slug, 'mode' => 'category'])
        ->set('brandIds', [$brand->id]);
    expect($component->get('brandIds'))->toBe([$brand->id]);
});

test('sorting orders products with a stable secondary key', function () {
    $category = Category::factory()->create();
    makeListingProduct($category, ['name' => 'الأغلى منتج', 'price' => 900, 'sales_count' => 1]);
    makeListingProduct($category, ['name' => 'الأرخص منتج', 'price' => 100, 'sales_count' => 50]);
    makeListingProduct($category, ['name' => 'الأوسط منتج', 'price' => 500, 'sales_count' => 10]);

    $html = $this->get('/categories/'.$category->slug.'?sort=price_asc')->getContent();
    expect(strpos($html, 'الأرخص منتج'))->toBeLessThan(strpos($html, 'الأوسط منتج'));
    expect(strpos($html, 'الأوسط منتج'))->toBeLessThan(strpos($html, 'الأغلى منتج'));

    $html = $this->get('/categories/'.$category->slug.'?sort=best_sellers')->getContent();
    expect(strpos($html, 'الأرخص منتج'))->toBeLessThan(strpos($html, 'الأوسط منتج'));
});

test('listing paginates at 24 products per page', function () {
    $category = Category::factory()->create();
    for ($i = 0; $i < 30; $i++) {
        makeListingProduct($category, ['name' => 'منتج رقم '.$i]);
    }

    $response = $this->get('/categories/'.$category->slug)->assertOk();
    expect(substr_count($response->getContent(), 'product-card'))->toBeGreaterThanOrEqual(24);
    $this->get('/categories/'.$category->slug.'?page=2')
        ->assertOk()
        ->assertSee('منتج رقم');
});

test('price range filter applies and filtered urls are noindex with canonical', function () {
    $category = Category::factory()->create();
    makeListingProduct($category, ['name' => 'منتج رخيص', 'price' => 50]);
    makeListingProduct($category, ['name' => 'منتج غالي', 'price' => 5000]);

    Livewire::test(ListingPage::class, ['slug' => $category->slug, 'mode' => 'category'])
        ->set('priceMax', '100')
        ->assertSee('منتج رخيص')
        ->assertDontSee('منتج غالي');

    $this->get('/categories/'.$category->slug.'?max=100')
        ->assertOk()
        ->assertSee('noindex', false)
        ->assertSee('rel="canonical"', false);
});

test('empty listing shows the empty-search scene with a whatsapp link', function () {
    Setting::create(['key' => 'whatsapp_number', 'value' => '201001234567']);
    $category = Category::factory()->create(['name' => 'تصنيف فارغ']);

    $this->get('/categories/'.$category->slug)
        ->assertOk()
        ->assertSee('مفيش نتايج')
        ->assertSee('data-scene="empty-search"', false)
        ->assertSee('wa.me/201001234567', false);
});

test('inactive products and inactive categories are excluded', function () {
    $category = Category::factory()->create();
    makeListingProduct($category, ['name' => 'منتج نشط']);
    makeListingProduct($category, ['name' => 'منتج موقوف', 'is_active' => false]);

    $this->get('/categories/'.$category->slug)
        ->assertSee('منتج نشط')
        ->assertDontSee('منتج موقوف');

    $inactive = Category::factory()->create(['is_active' => false]);
    $this->get('/categories/'.$inactive->slug)->assertNotFound();
});
