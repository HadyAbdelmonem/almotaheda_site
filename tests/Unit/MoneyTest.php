<?php

use App\Support\Money;

it('rounds half-up to 2 decimals', function () {
    expect(Money::of('1.005'))->toBe('1.01')
        ->and(Money::of('1.004'))->toBe('1.00')
        ->and(Money::of('2.675'))->toBe('2.68')
        ->and(Money::of('0.125'))->toBe('0.13');
});

it('adds and subtracts without float error', function () {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::sub('100.00', '0.01'))->toBe('99.99')
        ->and(Money::add('999999999.99', '0.01'))->toBe('1000000000.00');
});

it('multiplies by decimal quantities and rounds half-up', function () {
    expect(Money::mulQty('26.00', '2.5'))->toBe('65.00')
        ->and(Money::mulQty('18.00', '0.25'))->toBe('4.50')
        ->and(Money::mulQty('33.33', '3'))->toBe('99.99');
});

it('calculates percentages and discounts', function () {
    expect(Money::percent('200.00', '10'))->toBe('20.00')
        ->and(Money::percent('99.99', '10'))->toBe('10.00')
        ->and(Money::discount('149.00', '10'))->toBe('134.10');
});

it('formats amounts in the Egyptian display format', function () {
    expect(Money::format('1250'))->toBe('1,250 ج.م')
        ->and(Money::format('1250.5'))->toBe('1,250.5 ج.م')
        ->and(Money::format('99.99'))->toBe('99.99 ج.م')
        ->and(Money::format('1000000'))->toBe('1,000,000 ج.م');
});

it('compares amounts as decimals', function () {
    expect(Money::compare('10.00', '9.99'))->toBe(1)
        ->and(Money::compare('10.00', '10.0'))->toBe(0)
        ->and(Money::min('5.00', '7.00'))->toBe('5.00')
        ->and(Money::max('5.00', '7.00'))->toBe('7.00');
});
