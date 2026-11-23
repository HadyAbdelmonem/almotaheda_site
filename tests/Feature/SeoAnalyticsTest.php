<?php

use App\Enums\ReviewStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function seoSetSetting(string $key, mixed $value): void
{
    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    Cache::forget("setting.{$key}");
}

test('sitemap lists active catalog and pages, never _ui or inactive items', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $brand = Brand::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true, 'category_id' => $category->id, 'brand_id' => $brand->id]);
    $hidden = Product::factory()->create(['is_active' => false]);
    $page = Page::create(['slug' => 'about', 'title' => 'من نحن', 'content' => 'نص', 'is_active' => true]);

    $response = $this->get('/sitemap.xml')->assertOk()
        ->assertSee(route('categories.show', $category->slug), false)
        ->assertSee(route('brands.show', $brand->slug), false)
        ->assertSee(route('products.show', $product->slug), false)
        ->assertSee(route('pages.show', $page->slug), false);

    $response->assertDontSee('_ui', false)
        ->assertDontSee(route('products.show', $hidden->slug), false)
        ->assertDontSee('/admin', false);
});

test('robots.txt disallows private areas but never search or filter urls', function () {
    $response = $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /cart')
        ->assertSee('Disallow: /checkout')
        ->assertSee('Disallow: /account')
        ->assertSee('Sitemap: ');

    $response->assertDontSee('Disallow: /search');
});

test('product page carries canonical, meta description and Product + Breadcrumb JSON-LD', function () {
    $product = Product::factory()->create([
        'is_active' => true, 'price' => 250, 'stock_quantity' => 10,
        'meta_description' => 'وصف مخصص للمنتج', 'cost_price' => 777777,
    ]);

    $response = $this->get(route('products.show', $product->slug))->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('products.show', $product->slug).'">', false)
        ->assertSee('وصف مخصص للمنتج', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"priceCurrency":"EGP"', false)
        ->assertSee('https://schema.org/InStock', false)
        ->assertSee('"@type":"BreadcrumbList"', false);

    // No aggregateRating without approved reviews, and never cost data.
    $response->assertDontSee('aggregateRating', false)
        ->assertDontSee('777777', false);
});

test('product JSON-LD includes aggregateRating only from approved reviews', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5, 'status' => ReviewStatus::Approved]);
    $other = User::factory()->create();
    Review::create(['product_id' => $product->id, 'user_id' => $other->id, 'rating' => 1, 'status' => ReviewStatus::Pending]);

    $this->get(route('products.show', $product->slug))->assertOk()
        ->assertSee('"@type":"AggregateRating"', false)
        ->assertSee('"ratingValue":5', false)
        ->assertSee('"reviewCount":1', false);
});

test('homepage carries Organization/LocalBusiness JSON-LD', function () {
    $this->get('/')->assertOk()
        ->assertSee('"@type":"LocalBusiness"', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', false);
});

test('cart, login, register and track-order pages are noindex', function () {
    $this->get('/cart')->assertOk()->assertSee('noindex', false);
    $this->get('/login')->assertOk()->assertSee('noindex', false);
    $this->get('/register')->assertOk()->assertSee('noindex', false);
    $this->get('/track-order')->assertOk()->assertSee('noindex', false);
});

test('search page is noindex even without query parameters', function () {
    $this->get('/search')->assertOk()->assertSee('noindex', false);
});

test('no analytics or verification tags are rendered when settings are empty', function () {
    $this->get('/')->assertOk()
        ->assertDontSee('ga4-id', false)
        ->assertDontSee('googletagmanager', false)
        ->assertDontSee('google-site-verification', false)
        ->assertDontSee('data-ga-events', false);
});

test('GA4 meta and server events render only when a measurement id is configured', function () {
    seoSetSetting('ga4_measurement_id', 'G-TEST123');
    seoSetSetting('search_console_verification', 'verify-token-abc');

    $this->get('/')->assertOk()
        ->assertSee('ga4-id', false)
        ->assertSee('G-TEST123', false)
        ->assertSee('google-site-verification', false)
        ->assertSee('verify-token-abc', false);

    $product = Product::factory()->create(['is_active' => true, 'price' => 250, 'cost_price' => 777777]);
    $response = $this->get(route('products.show', $product->slug))->assertOk()
        ->assertSee('data-ga-events', false)
        ->assertSee('view_item', false);
    $response->assertDontSee('777777', false);
});

test('analytics payloads are allowlisted and exclude PII and cost data', function () {
    $product = Product::factory()->create(['price' => 250, 'cost_price' => 777777]);
    $viewItem = Analytics::viewItem($product);
    $json = json_encode($viewItem);
    expect($viewItem['event'])->toBe('view_item')
        ->and($json)->not->toContain('cost_price')
        ->and($json)->not->toContain('777777');

    $order = Order::create([
        'customer_name' => 'عميل سري الاسم', 'phone' => '01009998877', 'email' => 'secret@example.com',
        'governorate' => 'القاهرة', 'city' => 'مدينة نصر', 'address' => 'عنوان سري بالتفصيل',
        'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'new',
        'subtotal' => 200, 'discount_total' => 0, 'shipping_fee' => 50, 'total' => 250,
        'stock_restoration_status' => 'not_required', 'placed_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'name' => $product->name, 'sku' => $product->model_code,
        'unit_code' => 'piece', 'unit_label_ar' => 'قطعة', 'unit_price' => 250, 'cost_price' => 777777,
        'quantity' => 1, 'line_total' => 250,
    ]);

    $purchase = Analytics::purchase($order->fresh('items'));
    $json = json_encode($purchase);
    expect($purchase['event'])->toBe('purchase')
        ->and($purchase['params']['transaction_id'])->toBe($order->order_number)
        ->and($json)->not->toContain('cost_price')
        ->and($json)->not->toContain('777777')
        ->and($json)->not->toContain('01009998877')
        ->and($json)->not->toContain('secret@example.com')
        ->and($json)->not->toContain('عميل سري الاسم')
        ->and($json)->not->toContain('عنوان سري');
});

test('purchase event is rendered once per order session on the success page', function () {
    seoSetSetting('ga4_measurement_id', 'G-TEST123');
    $order = Order::create([
        'customer_name' => 'أحمد علي', 'phone' => '01001234567',
        'governorate' => 'القاهرة', 'city' => 'مدينة نصر', 'address' => 'شارع 1',
        'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'new',
        'subtotal' => 200, 'discount_total' => 0, 'shipping_fee' => 50, 'total' => 250,
        'stock_restoration_status' => 'not_required', 'placed_at' => now(),
    ]);

    $url = route('order.success', $order->order_number);
    $first = $this->withSession(['order.success.'.$order->order_number => true])->get($url)->assertOk();
    $first->assertSee('purchase', false)->assertSee('data-ga-events', false);

    // Same session, second view (reload / back): no purchase payload again.
    $second = $this->get($url)->assertOk();
    $second->assertDontSee('data-ga-events', false);
});
