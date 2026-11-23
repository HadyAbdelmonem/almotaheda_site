<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ui kit returns 404 outside the local environment', function () {
    // The test environment is "testing", so /_ui must not exist here.
    $this->get('/_ui')->assertNotFound();
});

test('ui kit renders in the local environment with components and diagnostics', function () {
    $this->app->instance('env', 'local');

    $response = $this->get('/_ui');

    $response->assertOk();
    $response->assertSee('data-diagnostics', false);
    $response->assertSee('wire-pulse', false);
    $response->assertSee('plug-socket', false);
    $response->assertSee('data-icon="drill"', false);
    $response->assertSee('data-icon="breaker"', false);
});

test('layout renders the server motion level from settings', function () {
    Setting::create(['key' => 'motion_level', 'value' => 'lite']);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-motion="lite"', false);
    // The downgrade-only inline script must be present (spec 11.3).
    $response->assertSee('prefers-reduced-motion: reduce', false);
    $response->assertSee('saveData', false);
});

test('layout defaults motion level to full when no setting exists', function () {
    $this->get('/')->assertSee('data-motion="full"', false);
});

test('icon whitelist keys all have an inline svg blade component', function () {
    foreach (array_keys(config('icons')) as $key) {
        $path = resource_path("views/components/icons/{$key}.blade.php");
        expect(file_exists($path))->toBeTrue("Missing icon component for {$key}");
    }
});

test('motion js modules and css rules exist', function () {
    foreach (['level', 'reveal', 'live', 'fx', 'fly-to-cart', 'sticky-bar', 'header', 'switch'] as $module) {
        expect(file_exists(resource_path("js/motion/{$module}.js")))->toBeTrue("Missing motion module {$module}");
    }

    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)
        ->toContain('@custom-variant motion-full')
        ->toContain('@custom-variant motion-any')
        ->toContain('@keyframes wire-pulse')
        ->toContain('@keyframes spark-burst')
        ->toContain('@keyframes bulb-flicker')
        ->toContain('html[data-motion="off"]');
});
