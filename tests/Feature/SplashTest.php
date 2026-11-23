<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The loading splash (added 2026-11-23 as an owner override of the spec
// preloader ban) was removed again the same day at the owner's explicit
// request: the site must open directly with its content. These tests pin
// the removal so the overlay does not creep back in.
test('home page renders content directly with no splash overlay', function () {
    Setting::create(['key' => 'store_name', 'value' => 'شركة الصفا للتوريدات الكهربائية']);

    $response = $this->get('/')->assertOk();

    $response->assertSee('شركة الصفا للتوريدات الكهربائية');
    $response->assertDontSee('data-splash', false);
    $response->assertDontSee('id="splash"', false);
    $response->assertDontSee('splash-wire', false);
    $response->assertDontSee('class="splash"', false);
});

test('splash component, hide module and styles are removed', function () {
    expect(file_exists(resource_path('views/components/splash.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('js/motion/splash.js')))->toBeFalse();

    expect(file_get_contents(resource_path('css/app.css')))->not->toContain('.splash');
    expect(file_get_contents(resource_path('js/app.js')))->not->toContain('splash');
});

test('no splash markup leaks into the filament admin layout', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('data-splash', false)
        ->assertDontSee('splash-wire', false);
});
