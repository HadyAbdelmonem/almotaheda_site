<?php

use App\Livewire\ContactForm;
use App\Livewire\WholesaleForm;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Setting;
use App\Models\WholesaleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function setSetting(string $key, mixed $value): void
{
    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    Cache::forget("setting.{$key}");
}

test('header renders store identity, phones and topbar from settings only', function () {
    setSetting('store_name', 'متجر النور للكهرباء');
    setSetting('phones', ['01001234567']);
    setSetting('topbar_text', 'عرض خاص النهاردة');

    $this->get('/')
        ->assertOk()
        ->assertSee('متجر النور للكهرباء')
        ->assertSee('01001234567')
        ->assertSee('عرض خاص النهاردة');
});

test('whatsapp float is hidden when the number is empty and shown when set', function () {
    setSetting('whatsapp_number', '');
    $this->get('/')->assertOk()->assertDontSee('wa.me/');

    setSetting('whatsapp_number', '201001234567');
    $this->get('/')->assertOk()->assertSee('https://wa.me/201001234567');
});

test('header nav and drawer list active categories from the cached tree', function () {
    $parent = Category::factory()->create(['name' => 'تأسيس كهرباء', 'is_active' => true]);
    Category::factory()->create(['name' => 'قسم مخفي', 'is_active' => false]);
    Category::factory()->create(['name' => 'أسلاك وكابلات', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee('تأسيس كهرباء')
        ->assertSee('أسلاك وكابلات')
        ->assertDontSee('قسم مخفي');
});

test('footer lists active static pages and hides inactive ones', function () {
    Page::create(['slug' => 'about', 'title' => 'من نحن', 'content' => '<p>نبذة</p>', 'is_active' => true]);
    Page::create(['slug' => 'secret', 'title' => 'صفحة مخفية', 'content' => 'x', 'is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('من نحن')
        ->assertDontSee('صفحة مخفية');
});

test('static page renders sanitized content and inactive page 404s', function () {
    Page::create([
        'slug' => 'shipping-policy', 'title' => 'سياسة الشحن', 'is_active' => true,
        'content' => '<p>الشحن خلال يومين</p><script>alert(1)</script><p onclick="evil()">نص</p>',
    ]);
    Page::create(['slug' => 'draft', 'title' => 'مسودة', 'content' => 'x', 'is_active' => false]);

    $response = $this->get('/pages/shipping-policy')->assertOk()->assertSee('سياسة الشحن')->assertSee('الشحن خلال يومين');
    expect($response->getContent())->not->toContain('alert(1)')->and($response->getContent())->not->toContain('evil()');
    $this->get('/pages/draft')->assertNotFound();
});

test('contact form stores a message with normalized phone', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'أحمد علي')
        ->set('phone', '٠١٠٠١٢٣٤٥٦٧')
        ->set('message', 'عايز أسأل عن قاطع 32 أمبير')
        ->call('submit')
        ->assertSet('sent', true);

    $message = ContactMessage::first();
    expect($message)->not->toBeNull()
        ->and($message->phone)->toBe('01001234567')
        ->and($message->is_read)->toBeFalse();
});

test('contact form rejects an invalid phone and stores nothing', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'أحمد')
        ->set('phone', '12345')
        ->set('message', 'رسالة')
        ->call('submit')
        ->assertHasErrors('phone');

    expect(ContactMessage::count())->toBe(0);
});

test('contact form honeypot pretends success but stores nothing', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'بوت')
        ->set('phone', '01001234567')
        ->set('message', 'سبام')
        ->set('website', 'http://spam.example')
        ->call('submit')
        ->assertSet('sent', true);

    expect(ContactMessage::count())->toBe(0);
});

test('wholesale form stores a request with status new', function () {
    Livewire::test(WholesaleForm::class)
        ->set('name', 'شركة البناء')
        ->set('phone', '+201001234567')
        ->set('company', 'البناء الحديث')
        ->set('monthlyVolume', 'حوالي 500 قطعة')
        ->call('submit')
        ->assertSet('sent', true);

    $request = WholesaleRequest::first();
    expect($request)->not->toBeNull()
        ->and($request->phone)->toBe('01001234567')
        ->and($request->status)->toBe('new');
});

test('contact form is throttled after repeated attempts', function () {
    $component = Livewire::test(ContactForm::class)
        ->set('name', 'أحمد')->set('phone', '01001234567')->set('message', 'أهلًا');

    for ($i = 0; $i < 5; $i++) {
        $component->call('submit');
    }
    $component->call('submit')->assertHasErrors('message');
});
