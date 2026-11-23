<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home page renders the splash overlay with the logo and store name', function () {
    Setting::create(['key' => 'store_name', 'value' => 'شركة الصفا للتوريدات الكهربائية']);

    $response = $this->get('/')->assertOk();

    // Splash markup (owner override extra) with the designed logo fallback
    // from store_logo_url() and the store name from settings.
    $response->assertSee('data-splash', false);
    $response->assertSee('id="splash"', false);
    $response->assertSee('branding/logo.webp', false);
    $response->assertSee('شركة الصفا للتوريدات الكهربائية');
    $response->assertSee('splash-wire', false);
});

test('splash assets and hide module exist', function () {
    expect(file_exists(resource_path('js/motion/splash.js')))->toBeTrue();
    expect(file_exists(resource_path('views/components/splash.blade.php')))->toBeTrue();

    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toContain('.splash')->toContain('@keyframes splash-autohide');
});

test('splash markup does not leak into the filament admin layout', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('data-splash', false)
        ->assertDontSee('splash-wire', false);
});
