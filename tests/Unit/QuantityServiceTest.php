<?php

use App\Services\QuantityService;

beforeEach(function () {
    $this->qty = new QuantityService;
});

it('normalizes decimal quantities to at most 3 places', function () {
    expect($this->qty->normalize('2.500'))->toBe('2.5')
        ->and($this->qty->normalize('3'))->toBe('3')
        ->and($this->qty->normalize('0.25'))->toBe('0.25')
        ->and($this->qty->toStorage('2.5'))->toBe('2.500');
});

it('rejects quantities with more than 3 decimal places', function () {
    $this->qty->normalize('1.0001');
})->throws(InvalidArgumentException::class);

it('validates whole-unit steps for piece products', function () {
    expect($this->qty->validate('3', '1', '1'))->toBe('3');
    $this->qty->validate('1.5', '1', '1');
})->throws(InvalidArgumentException::class);

it('validates fractional steps for meter products', function () {
    expect($this->qty->validate('2.5', '1', '0.25'))->toBe('2.5')
        ->and($this->qty->validate('0.5', '0.5', '0.25'))->toBe('0.5');
    $this->qty->validate('2.3', '1', '0.25');
})->throws(InvalidArgumentException::class);

it('enforces minimum and maximum quantities', function () {
    expect(fn () => $this->qty->validate('0.5', '1', '0.25'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->qty->validate('11', '1', '1', '10'))->toThrow(InvalidArgumentException::class);
});

it('adds and subtracts quantities without float error', function () {
    expect($this->qty->add('0.1', '0.2'))->toBe('0.3')
        ->and($this->qty->sub('850.5', '0.25'))->toBe('850.25')
        ->and($this->qty->compare('2.5', '2.50'))->toBe(0);
});

it('clamps quantities to available stock on step boundaries', function () {
    expect($this->qty->clampToStock('10', '7.6', '0.25'))->toBe('7.5')
        ->and($this->qty->clampToStock('5', '10', '1'))->toBe('5');
});
