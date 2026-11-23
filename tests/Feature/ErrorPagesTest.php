<?php

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('404 page is the Arabic electric scene with working search and category links', function () {
    Category::factory()->create(['name' => 'إنارة وديكور', 'is_active' => true]);

    $response = $this->get('/definitely-not-a-page')->assertNotFound()
        ->assertSee('الصفحة دي مش موجودة')
        ->assertSee('يبدو إن الفيشة اتشالت')
        ->assertSee('data-scene="404"', false)
        // The plug is a real button and the bulb is bound to it (M70).
        ->assertSee('plug-toggled', false)
        ->assertSee(':data-on="lit"', false)
        // Search form posts to the real search route without JS.
        ->assertSee('action="'.route('search').'"', false)
        ->assertSee('إنارة وديكور')
        ->assertSee('ارجع للرئيسية');

    // No technical details leak into the error page.
    $response->assertDontSee('Symfony');
});

test('500 view renders the Arabic breaker scene without technical details', function () {
    $html = view('errors.500')->render();

    expect($html)->toContain('حصل عطل مؤقت')
        ->toContain('الفيوز فصل — جرّب تاني بعد لحظات')
        ->toContain('data-scene="500"')
        ->toContain('switch:on')
        ->not->toContain('Stack trace')
        ->not->toContain('Exception');
});

test('empty states for cart, search and wishlist use their E16 scenes', function () {
    $this->get('/cart')->assertOk()->assertSee('data-scene="empty-cart"', false);
    $this->get('/search?q=zzzznothing')->assertOk()->assertSee('data-scene="empty-search"', false);

    $user = User::factory()->create();
    $this->actingAs($user)->get('/account/wishlist')->assertOk()
        ->assertSee('data-scene="empty-wishlist"', false);
});

test('home hero carries the M21 circuit overlay and brands rail carries the M25 wire pulse', function () {
    Banner::create(['title' => 'عرض', 'position' => BannerPosition::Hero, 'is_active' => true, 'sort_order' => 1]);
    Brand::factory()->create(['is_active' => true]);

    $this->get('/')->assertOk()
        ->assertSee('data-hero-ambient', false)
        ->assertSee('wire-pulse', false);
});
