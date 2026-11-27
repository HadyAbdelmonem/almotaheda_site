<?php

use App\Livewire\ProductPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makePageProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'name' => 'قاطع كهربائي شنايدر',
        'model_code' => 'MC'.fake()->unique()->numberBetween(10000, 99999),
        'price' => 250,
        'cost_price' => 98765,
        'stock_quantity' => 20,
    ], $attrs));
}

test('product page renders with price, stock and unit label', function () {
    $product = makePageProduct();

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee('قاطع كهربائي شنايدر')
        ->assertSee($product->model_code)
        ->assertSee('250 ج.م')
        ->assertSee('متوفر')
        ->assertDontSee('98765')
        ->assertDontSee('cost_price');
});

test('inactive or missing product returns 404', function () {
    $product = makePageProduct(['is_active' => false]);

    $this->get('/products/'.$product->slug)->assertNotFound();
    $this->get('/products/does-not-exist')->assertNotFound();
});

test('variant selection updates price, sku and stock state', function () {
    $product = makePageProduct(['has_variants' => true, 'price' => 200]);
    $white = ProductVariant::create([
        'product_id' => $product->id, 'name' => 'أبيض', 'sku' => 'SW-WHITE-1',
        'price' => 300, 'stock_quantity' => 10, 'is_active' => true, 'sort_order' => 1,
    ]);
    $black = ProductVariant::create([
        'product_id' => $product->id, 'name' => 'أسود', 'sku' => 'SW-BLACK-1',
        'price' => 450, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 2,
    ]);

    $component = Livewire::test(ProductPage::class, ['slug' => $product->slug])
        ->assertSee('تبدأ من')
        ->assertSee('300 ج.م');

    $component->call('selectVariant', $black->id)
        ->assertSee('450 ج.م')
        ->assertSee('SW-BLACK-1')
        ->assertSee('غير متوفر');

    $component->call('selectVariant', $white->id)
        ->assertSee('300 ج.م')
        ->assertSee('SW-WHITE-1')
        ->assertSee('متوفر');
});

test('inactive variant cannot be selected', function () {
    $product = makePageProduct(['has_variants' => true]);
    $inactive = ProductVariant::create([
        'product_id' => $product->id, 'name' => 'ذهبي', 'sku' => 'SW-GOLD-1',
        'price' => 999, 'stock_quantity' => 5, 'is_active' => false, 'sort_order' => 1,
    ]);

    Livewire::test(ProductPage::class, ['slug' => $product->slug])
        ->call('selectVariant', $inactive->id)
        ->assertSet('variantId', null)
        ->assertDontSee('999 ج.م');
});

test('valid sale shows struck regular price and discount badge, expired sale does not', function () {
    $onSale = makePageProduct(['price' => 400, 'sale_price' => 300, 'sale_ends_at' => now()->addDays(3)]);
    $this->get('/products/'.$onSale->slug)
        ->assertOk()
        ->assertSee('300 ج.م')
        ->assertSee('400 ج.م')
        ->assertSee('-25%');

    $expired = makePageProduct(['price' => 400, 'sale_price' => 300, 'sale_ends_at' => now()->subDay()]);
    $this->get('/products/'.$expired->slug)
        ->assertOk()
        ->assertSee('400 ج.م')
        ->assertDontSee('-25%');
});

test('fractional meter quantity and tier pricing compute on the server', function () {
    $product = makePageProduct([
        'unit_code' => 'meter', 'min_order_quantity' => 1, 'quantity_step' => 0.25,
        'price' => 100, 'stock_quantity' => 500,
    ]);
    ProductPriceTier::create(['product_id' => $product->id, 'min_qty' => 10, 'discount_percent' => 10]);

    Livewire::test(ProductPage::class, ['slug' => $product->slug])
        ->set('quantity', '2.5')
        ->assertSet('quantity', '2.5')
        ->assertSee('250 ج.م') // line total 2.5 x 100
        ->set('quantity', '10')
        ->assertSee('90 ج.م') // tier unit price after 10% discount
        ->assertSee('900 ج.م') // line total
        ->set('quantity', '0.3') // not a multiple of step 0.25 -> rejected to min
        ->assertSet('quantity', '1');
});

test('quantity is clamped to available stock', function () {
    $product = makePageProduct(['stock_quantity' => 5]);

    Livewire::test(ProductPage::class, ['slug' => $product->slug])
        ->set('quantity', '50')
        ->assertSet('quantity', '5');
});

test('out of stock product cannot be added and whatsapp asks about availability', function () {
    Setting::create(['key' => 'whatsapp_number', 'value' => '201001234567']);
    $product = makePageProduct(['stock_quantity' => 0]);

    $response = $this->get('/products/'.$product->slug);
    $response->assertOk()
        ->assertSee('غير متوفر')
        ->assertSee('اسأل عن التوفر')
        ->assertSee('wa.me/201001234567', false);
    // The pre-filled WhatsApp text asks about availability for this product.
    expect(urldecode($response->getContent()))->toContain('أسأل عن توفر');
});

test('whatsapp button is hidden when no number is configured', function () {
    $product = makePageProduct();

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertDontSee('wa.me/');
});

test('technical attributes and descriptive specs render in the spec table', function () {
    $category = Category::factory()->create();
    $attribute = CategoryAttribute::create([
        'category_id' => $category->id, 'key' => 'current_rating_a', 'name_ar' => 'التيار',
        'data_type' => 'number', 'unit_label' => 'A', 'is_filterable' => true, 'is_active' => true,
    ]);
    $product = makePageProduct(['category_id' => $category->id, 'specs' => [['label' => 'النوع', 'value' => 'ثنائي']]]);
    ProductAttributeValue::create([
        'product_id' => $product->id, 'category_attribute_id' => $attribute->id, 'value_number' => 16,
    ]);

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee('التيار')
        ->assertSee('16')
        ->assertSee('النوع')
        ->assertSee('ثنائي');
});

test('only approved reviews are shown with the average rating', function () {
    $product = makePageProduct();
    $user = User::factory()->create();
    Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5, 'comment' => 'ممتاز جدًا', 'status' => 'approved']);
    Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 1, 'comment' => 'تعليق مرفوض', 'status' => 'rejected']);

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee('ممتاز جدًا')
        ->assertDontSee('تعليق مرفوض');
});

test('related products come from the same category, preferring the same brand', function () {
    $brand = Brand::factory()->create();
    $otherBrand = Brand::factory()->create();
    $category = Category::factory()->create();
    $product = makePageProduct(['category_id' => $category->id, 'brand_id' => $brand->id]);
    $sameBrand = makePageProduct(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'منتج نفس الماركة', 'sales_count' => 1]);
    makePageProduct(['category_id' => $category->id, 'brand_id' => $otherBrand->id, 'name' => 'منتج ماركة أخرى', 'sales_count' => 999]);

    $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();
    expect(strpos($html, 'منتج نفس الماركة'))->toBeLessThan(strpos($html, 'منتج ماركة أخرى'));
});

test('gallery shows edge navigation arrows and padded image only with multiple images', function () {
    // Owner request 2026-11-27: prev/next arrows on the gallery edges and a
    // slightly reduced, breathing-room image (p-8/md:p-10 instead of p-4).
    $product = makePageProduct();
    $product->addMedia(UploadedFile::fake()->image('one.jpg', 800, 800))->toMediaCollection('gallery');
    $product->addMedia(UploadedFile::fake()->image('two.jpg', 800, 800))->toMediaCollection('gallery');

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('data-gallery-prev', false)
        ->assertSee('data-gallery-next', false)
        ->assertSee('object-contain p-8 md:p-10', false);

    $single = makePageProduct();
    $single->addMedia(UploadedFile::fake()->image('only.jpg', 800, 800))->toMediaCollection('gallery');

    $this->get(route('products.show', $single->slug))
        ->assertOk()
        ->assertDontSee('data-gallery-prev', false)
        ->assertDontSee('data-gallery-next', false);
});
