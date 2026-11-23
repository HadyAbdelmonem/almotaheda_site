<?php

use Database\Seeders\ProductionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeded settings contain the owner store identity (Facebook page)', function () {
    $this->seed(SettingSeeder::class);

    expect(setting('store_name'))->toBe('شركة الصفا للتوريدات الكهربائية')
        ->and(setting('address'))->toBe('التجمع الخامس، القاهرة')
        ->and(setting('whatsapp_number'))->toBe('201033808633')
        ->and(setting('phones'))->toBe(['01033808633'])
        ->and(setting('email'))->toBe('hadyabdelmonem11@gmail.com')
        ->and(setting('bank_transfer_details'))->toBe('التحويل عبر InstaPay أو فودافون كاش على الرقم 01033808633')
        ->and(setting('social_links')['facebook'] ?? null)->toContain('facebook.com/people/')
        // Owner kept the spec defaults for these:
        ->and((int) setting('free_shipping_threshold'))->toBe(3000)
        ->and((int) setting('return_policy_days'))->toBe(7)
        // No Paymob merchant account: card payment stays disabled.
        ->and((bool) setting('payment_card_enabled'))->toBeFalse()
        ->and((bool) setting('payment_cod_enabled'))->toBeTrue()
        ->and((bool) setting('payment_bank_enabled'))->toBeTrue();
});

test('seeded about page carries the Facebook page bio', function () {
    $this->seed(\Database\Seeders\PageSeeder::class);

    $about = \App\Models\Page::where('slug', 'about')->first();
    expect($about)->not->toBeNull()
        ->and($about->content)->toContain('متخصصون في توريد جميع المستلزمات الكهربائية');
});

test('production seeder seeds the same store identity without demo products', function () {
    $this->seed(ProductionSeeder::class);

    expect(setting('store_name'))->toBe('شركة الصفا للتوريدات الكهربائية')
        ->and(setting('whatsapp_number'))->toBe('201033808633')
        ->and(\App\Models\Product::count())->toBe(0);
});

test('storefront renders the default branding logo when the logo setting is empty', function () {
    $this->get('/')->assertOk()
        ->assertSee('branding/logo.webp', false);

    // A configured logo setting wins over the default mark.
    \App\Models\Setting::updateOrCreate(['key' => 'logo'], ['value' => 'branding/custom-logo.webp']);
    \Illuminate\Support\Facades\Cache::forget('setting.logo');
    $this->get('/')->assertOk()->assertSee('branding/custom-logo.webp', false);
});

test('storefront shows the seeded WhatsApp float and store name', function () {
    $this->seed(SettingSeeder::class);

    $this->get('/')->assertOk()
        ->assertSee('شركة الصفا للتوريدات الكهربائية')
        ->assertSee('wa.me/201033808633', false);
});
