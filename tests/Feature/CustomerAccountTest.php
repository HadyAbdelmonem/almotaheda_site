<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Livewire\Account\AccountAddresses;
use App\Livewire\Account\AccountOrders;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\RegisterPage;
use App\Livewire\ReviewForm;
use App\Livewire\TrackOrderPage;
use App\Livewire\WishlistButton;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\OrderService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function p7User(array $attrs = []): User
{
    return User::factory()->create(array_merge(['password' => 'secret-password'], $attrs));
}

function p7OrderFor(User $user, OrderStatus $status = OrderStatus::New, ?Product $product = null): Order
{
    $product ??= Product::factory()->create(['price' => 100, 'stock_quantity' => 50, 'track_stock' => true]);
    $rate = ShippingRate::firstOrCreate(['name_ar' => 'القاهرة'], ['fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);
    $order = app(OrderService::class)->create(
        ['name' => $user->name, 'phone' => $user->phone ?? '01001234567', 'phone_alt' => null, 'email' => $user->email, 'city' => 'مدينة نصر', 'address' => 'شارع 1'],
        [['product_id' => $product->id, 'variant_id' => null, 'quantity' => '1']],
        null, $rate->id, PaymentMethod::Cod, $user->id,
    );
    if ($status !== OrderStatus::New) {
        $svc = app(OrderService::class);
        $path = match ($status) {
            OrderStatus::Cancelled => [OrderStatus::Cancelled],
            OrderStatus::Delivered => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered],
            default => [],
        };
        foreach ($path as $to) {
            $order = $svc->changeStatus($order, $to);
        }
    }

    return $order;
}

test('login works with phone (normalized) and with email', function () {
    $user = p7User(['phone' => '01001234567', 'email' => 'ahmed@example.com']);

    Livewire::test(LoginPage::class)
        ->set('identifier', '+20 100 123 4567')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('account.orders'));
    expect(auth()->id())->toBe($user->id);
    auth()->logout();

    Livewire::test(LoginPage::class)
        ->set('identifier', 'AHMED@example.com')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('account.orders'));
    expect(auth()->id())->toBe($user->id);
});

test('login rejects wrong password with a generic error', function () {
    p7User(['phone' => '01001234567']);
    Livewire::test(LoginPage::class)
        ->set('identifier', '01001234567')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('identifier');
    expect(auth()->guest())->toBeTrue();
});

test('register with phone creates the account and logs in; duplicate identifier rejected', function () {
    Livewire::test(RegisterPage::class)
        ->set('name', 'محمود')
        ->set('identifier', '01112345678')
        ->set('password', 'secret-password')
        ->set('passwordConfirmation', 'secret-password')
        ->call('register')
        ->assertRedirect(route('account.orders'));

    expect(User::where('phone', '01112345678')->exists())->toBeTrue();
    auth()->logout();

    Livewire::test(RegisterPage::class)
        ->set('name', 'محمود تاني')
        ->set('identifier', '01112345678')
        ->set('password', 'secret-password')
        ->set('passwordConfirmation', 'secret-password')
        ->call('register')
        ->assertHasErrors('identifier');
});

test('account pages require auth', function () {
    $this->get('/account/orders')->assertRedirect(route('login'));
    $this->get('/account/addresses')->assertRedirect(route('login'));
    $this->get('/account/wishlist')->assertRedirect(route('login'));
    $this->get('/account/profile')->assertRedirect(route('login'));
});

test('customer cannot see another customer order or address (404, no leakage)', function () {
    $owner = p7User();
    $other = p7User();
    $order = p7OrderFor($owner);
    $owner->addresses()->create(['label' => 'البيت', 'recipient_name' => $owner->name, 'phone' => '01001234567', 'governorate' => 'القاهرة', 'city' => 'مدينة نصر', 'address_line' => 'شارع 1', 'is_default' => true]);

    $this->actingAs($other);
    $this->get(route('account.orders.show', $order->order_number))->assertNotFound();

    // Address editing is scoped to the owner: another user's address id 404s.
    Livewire::test(AccountAddresses::class)->call('edit', $owner->addresses->first()->id)->assertNotFound();
    expect($other->addresses()->count())->toBe(0);
});

test('customer can cancel only own order while new; confirmed orders refuse with an error toast', function () {
    $user = p7User();
    $order = p7OrderFor($user);
    $this->actingAs($user);

    Livewire::test(AccountOrders::class)->call('cancelOrder', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    $order2 = p7OrderFor($user);
    app(OrderService::class)->changeStatus($order2, OrderStatus::Confirmed);
    Livewire::test(AccountOrders::class)->call('cancelOrder', $order2->id)
        ->assertDispatched('toast');
    expect($order2->fresh()->status)->toBe(OrderStatus::Confirmed);

    // Another user's order id cannot be cancelled either (scoped 404).
    $foreign = p7OrderFor(p7User());
    Livewire::test(AccountOrders::class)->call('cancelOrder', $foreign->id)->assertNotFound();
    expect($foreign->fresh()->status)->toBe(OrderStatus::New);
});

test('guest order with matching phone is NOT attached to a later account', function () {
    $rate = ShippingRate::firstOrCreate(['name_ar' => 'القاهرة'], ['fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'track_stock' => true]);
    $guestOrder = app(OrderService::class)->create(
        ['name' => 'زائر', 'phone' => '01009998877', 'phone_alt' => null, 'email' => null, 'city' => 'المعادي', 'address' => 'شارع 9'],
        [['product_id' => $product->id, 'variant_id' => null, 'quantity' => '1']],
        null, $rate->id, PaymentMethod::Cod, null,
    );

    $user = p7User(['phone' => '01009998877']);
    $this->actingAs($user)->get(route('account.orders'))->assertOk()->assertDontSee($guestOrder->order_number);
    $this->get(route('account.orders.show', $guestOrder->order_number))->assertNotFound();
});

test('track-order needs both matching order number and phone; mismatch is one generic message', function () {
    $user = p7User();
    $order = p7OrderFor($user);

    Livewire::test(TrackOrderPage::class)
        ->set('orderNumber', $order->order_number)
        ->set('phone', '01001234567')
        ->call('track')
        ->assertSee($order->status->label())
        ->assertSee($order->order_number);

    $wrongPhone = Livewire::test(TrackOrderPage::class)
        ->set('orderNumber', $order->order_number)
        ->set('phone', '01007776655')
        ->call('track');
    $wrongPhone->assertSee(__('store.track_mismatch'))->assertDontSee('إجمالي');

    $wrongNumber = Livewire::test(TrackOrderPage::class)
        ->set('orderNumber', 'ORD-1999-99999')
        ->set('phone', '01001234567')
        ->call('track');
    $wrongNumber->assertSee(__('store.track_mismatch'));
});

test('reviews: pending on submit, one per user, verified only with a delivered order, aggregates count approved only', function () {
    $buyer = p7User();
    $walkIn = p7User();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 50, 'track_stock' => true]);
    p7OrderFor($buyer, OrderStatus::Delivered, $product);

    $svc = app(ReviewService::class);
    $review = $svc->submit($buyer, $product, 5, 'ممتاز');
    expect($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->is_verified_purchase)->toBeTrue();

    $unverified = $svc->submit($walkIn, $product, 4, null);
    expect($unverified->is_verified_purchase)->toBeFalse();

    expect(fn () => $svc->submit($buyer, $product, 3, 'تاني'))->toThrow(InvalidArgumentException::class);

    expect($svc->aggregate($product))->toBe(['count' => 0, 'average' => 0.0]);
    $review->update(['status' => ReviewStatus::Approved]);
    $agg = $svc->aggregate($product);
    expect($agg['count'])->toBe(1)->and($agg['average'])->toBe(5.0);

    // Storefront product page shows approved reviews only.
    $this->get(route('products.show', $product->slug))->assertOk()->assertSee('ممتاز');
});

test('review form component enforces auth and duplicate rule', function () {
    $user = p7User();
    $product = Product::factory()->create();
    $this->actingAs($user);

    Livewire::test(ReviewForm::class, ['productId' => $product->id])
        ->set('rating', 5)->set('comment', 'كويس')->call('submit');
    expect(Review::count())->toBe(1);

    Livewire::test(ReviewForm::class, ['productId' => $product->id])
        ->set('rating', 4)->call('submit')
        ->assertSee(__('store.review_already_exists'));
    expect(Review::count())->toBe(1);
});

test('addresses: single default is enforced transactionally', function () {
    $user = p7User();
    $this->actingAs($user);
    ShippingRate::firstOrCreate(['name_ar' => 'القاهرة'], ['fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);

    $fill = fn ($c, $label, $default) => $c->set('label', $label)->set('recipientName', 'أحمد')->set('phone', '01001234567')
        ->set('governorate', 'القاهرة')->set('city', 'مدينة نصر')->set('addressLine', 'شارع 1')->set('isDefault', $default)->call('save');

    $fill(Livewire::test(AccountAddresses::class), 'البيت', true);
    $fill(Livewire::test(AccountAddresses::class), 'الشغل', true);

    expect($user->addresses()->count())->toBe(2)
        ->and($user->addresses()->where('is_default', true)->count())->toBe(1)
        ->and($user->addresses()->where('is_default', true)->first()->label)->toBe('الشغل');
});

test('wishlist toggle adds and removes; guests are redirected to login', function () {
    $user = p7User();
    $product = Product::factory()->create();

    Livewire::test(WishlistButton::class, ['productId' => $product->id])
        ->call('toggle')->assertRedirect(route('login'));

    $this->actingAs($user);
    $component = Livewire::test(WishlistButton::class, ['productId' => $product->id]);
    $component->call('toggle');
    expect(Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->exists())->toBeTrue()
        ->and($component->get('active'))->toBeTrue();
    $component->call('toggle');
    expect(Wishlist::count())->toBe(0);

    // Wishlist page renders the empty scene (M73) when empty.
    $this->get(route('account.wishlist'))->assertOk()->assertSee(__('store.empty_wishlist'));
});

test('order detail page renders the circuit timeline (M64) and account order list', function () {
    $user = p7User();
    $order = p7OrderFor($user);
    $this->actingAs($user);

    $this->get(route('account.orders'))->assertOk()->assertSee($order->order_number);
    $this->get(route('account.orders.show', $order->order_number))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee(__('store.status_timeline'));
});

test('admin can moderate reviews in Filament (approve action)', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = p7User();
    $product = Product::factory()->create();
    $review = app(ReviewService::class)->submit($user, $product, 5, 'رائع');

    $this->actingAs($admin);
    Livewire::test(ListReviews::class)
        ->callTableAction('approve', $review);
    expect($review->fresh()->status)->toBe(ReviewStatus::Approved);
});
