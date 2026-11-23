<?php

use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Services\PricingService;
use App\Services\QuantityService;

beforeEach(function () {
    $this->pricing = new PricingService(new QuantityService);
});

function makeProduct(array $attrs = [], array $tiers = []): Product
{
    $product = new Product(array_merge([
        'name' => 'Test product',
        'price' => '200.00',
        'sale_price' => null,
        'sale_ends_at' => null,
        'unit_code' => 'piece',
    ], $attrs));
    $product->setRelation('priceTiers', collect($tiers));

    return $product;
}

it('returns the regular price when there is no sale or tier', function () {
    $result = $this->pricing->unitPrice(makeProduct(), null, '1');
    expect($result['unit_price'])->toBe('200.00')
        ->and($result['sale_applied'])->toBeFalse()
        ->and($result['discount_percent'])->toBeNull();
});

it('applies a valid sale price', function () {
    $result = $this->pricing->unitPrice(makeProduct(['sale_price' => '150.00']), null, '1');
    expect($result['unit_price'])->toBe('150.00')
        ->and($result['sale_applied'])->toBeTrue()
        ->and($result['discount_percent'])->toBe('25.00');
});

it('ignores an expired sale price', function () {
    $result = $this->pricing->unitPrice(makeProduct([
        'sale_price' => '150.00',
        'sale_ends_at' => now()->subDay(),
    ]), null, '1');
    expect($result['unit_price'])->toBe('200.00')
        ->and($result['sale_applied'])->toBeFalse();
});

it('ignores sale prices that are not lower than the regular price', function () {
    expect($this->pricing->unitPrice(makeProduct(['sale_price' => '200.00']), null, '1')['sale_applied'])->toBeFalse()
        ->and($this->pricing->unitPrice(makeProduct(['sale_price' => '250.00']), null, '1')['unit_price'])->toBe('200.00');
});

it('uses the variant price and the variant own sale price and end date', function () {
    $variant = new ProductVariant([
        'price' => '300.00',
        'sale_price' => '240.00',
        'sale_ends_at' => now()->addDays(3),
    ]);
    $result = $this->pricing->unitPrice(makeProduct(['sale_price' => '100.00']), $variant, '1');
    expect($result['regular'])->toBe('300.00')
        ->and($result['unit_price'])->toBe('240.00')
        ->and($result['sale_applied'])->toBeTrue();
});

it('falls back to the product sale when the variant has no own sale price', function () {
    $variant = new ProductVariant(['price' => '300.00', 'sale_price' => null]);
    $result = $this->pricing->unitPrice(makeProduct(['sale_price' => '180.00']), $variant, '1');
    // Product sale (180) is compared against the variant regular price (300).
    expect($result['unit_price'])->toBe('180.00')
        ->and($result['sale_applied'])->toBeTrue();
});

it('ignores an expired variant sale even if the product sale is valid', function () {
    $variant = new ProductVariant([
        'price' => '300.00',
        'sale_price' => '240.00',
        'sale_ends_at' => now()->subHour(),
    ]);
    $result = $this->pricing->unitPrice(makeProduct(['sale_price' => '180.00']), $variant, '1');
    expect($result['unit_price'])->toBe('300.00')
        ->and($result['sale_applied'])->toBeFalse();
});

it('applies the greatest matching quantity tier and stacks it with a sale', function () {
    $tiers = [
        new ProductPriceTier(['min_qty' => '10', 'discount_percent' => '5']),
        new ProductPriceTier(['min_qty' => '25', 'discount_percent' => '10']),
    ];
    $product = makeProduct(['sale_price' => '150.00'], $tiers);

    $at10 = $this->pricing->unitPrice($product, null, '10');
    $at25 = $this->pricing->unitPrice($product, null, '25');
    $at5 = $this->pricing->unitPrice($product, null, '5');

    expect($at10['unit_price'])->toBe('142.50')   // 150 - 5%
        ->and($at25['unit_price'])->toBe('135.00') // 150 - 10%
        ->and($at5['unit_price'])->toBe('150.00')
        ->and($at25['tier_discount_percent'])->toBe('10.00');
});

it('calculates line totals for fractional quantities', function () {
    expect($this->pricing->lineTotal('26.00', '2.5'))->toBe('65.00')
        ->and($this->pricing->lineTotal('18.00', '0.25'))->toBe('4.50');
});

it('rounds tier-discounted unit prices half-up', function () {
    $tiers = [new ProductPriceTier(['min_qty' => '5', 'discount_percent' => '10'])];
    $product = makeProduct(['price' => '99.99'], $tiers);
    // 99.99 - 10% = 89.991 -> 89.99
    expect($this->pricing->unitPrice($product, null, '5')['unit_price'])->toBe('89.99');
});
