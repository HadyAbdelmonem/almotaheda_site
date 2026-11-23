<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderSuccessController;
use App\Http\Controllers\PageController;
use App\Livewire\Account\AccountAddresses;
use App\Livewire\Account\AccountOrders;
use App\Livewire\Account\AccountProfile;
use App\Livewire\Account\AccountWishlist;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\RegisterPage;
use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Livewire\ListingPage;
use App\Livewire\ProductPage;
use App\Livewire\TrackOrderPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// SEO endpoints (spec §9): dynamic sitemap + robots.
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

// UI kit (spec §11.11): local environment only, 404 everywhere else.
Route::get('/_ui', function () {
    abort_unless(app()->environment('local'), 404);

    return view('ui.index');
});

Route::get('/products/{slug}', ProductPage::class)->name('products.show');

Route::get('/categories/{slug}', ListingPage::class)->defaults('mode', 'category')->name('categories.show');
Route::get('/brands/{slug}', ListingPage::class)->defaults('mode', 'brand')->name('brands.show');

Route::get('/cart', CartPage::class)->name('cart');

Route::get('/checkout', CheckoutPage::class)->name('checkout');
Route::get('/order/success/{orderNumber}', [OrderSuccessController::class, 'show'])->name('order.success');

// Printable invoice (spec §7.2): authenticated admins only (checked in controller).
Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show'])->middleware('auth')->name('orders.invoice');

Route::get('/search', ListingPage::class)->defaults('mode', 'search')->name('search');

Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');

Route::view('/contact', 'contact')->name('contact');
Route::view('/wholesale', 'wholesale')->name('wholesale');

// Customer auth (Phase 7): one identifier (phone or email). Password
// reset stays with Laravel's default email flow when mail is configured.
Route::middleware('guest')->group(function () {
    Route::get('/login', LoginPage::class)->name('login');
    Route::get('/register', RegisterPage::class)->name('register');
});
Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

// Guest order tracking: order number + phone, generic mismatch (§4.9).
Route::get('/track-order', TrackOrderPage::class)->name('track-order');

// Payments (Phase 10, spec §4.8). The callback is the only route that
// can change online payment state; it is CSRF-exempt (exact route,
// bootstrap/app.php) and authenticated by HMAC verification. The
// return page is display-only. Card payment stays disabled until a
// merchant account + credentials + verified test callback exist.
Route::post('/payments/paymob/callback', [\App\Http\Controllers\PaymobController::class, 'callback'])->name('payments.paymob.callback');
Route::get('/payments/paymob/pay/{orderNumber}', [\App\Http\Controllers\PaymobController::class, 'pay'])->name('payments.paymob.pay');
Route::get('/payments/paymob/return/{orderNumber}', [\App\Http\Controllers\PaymobController::class, 'returnPage'])->name('payments.paymob.return');

// Bank-transfer receipts: private upload (owner / tracked guest) and
// admin-only viewing through an authorized route (Phase 10).
Route::post('/orders/{orderNumber}/receipt', [\App\Http\Controllers\ReceiptController::class, 'upload'])->name('orders.receipt.upload');
Route::get('/admin/orders/{order}/receipt', [\App\Http\Controllers\ReceiptController::class, 'show'])->middleware('auth')->name('orders.receipt.show');

// Customer account (auth + ownership enforced inside components).
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/orders', AccountOrders::class)->name('orders');
    Route::get('/orders/{orderNumber}', AccountOrders::class)->name('orders.show');
    Route::get('/addresses', AccountAddresses::class)->name('addresses');
    Route::get('/wishlist', AccountWishlist::class)->name('wishlist');
    Route::get('/profile', AccountProfile::class)->name('profile');
});
