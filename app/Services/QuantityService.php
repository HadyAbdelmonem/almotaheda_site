<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Decimal quantity validation and arithmetic (spec §4.11).
 * Quantities are decimal strings with up to 3 fractional digits.
 * All arithmetic uses BCMath on strings — never binary floats.
 */
class QuantityService
{
    public const SCALE = 3;

    /**
     * Normalize a quantity to a canonical decimal string with at most
     * 3 fractional digits (trailing zeros trimmed, but at least "0").
     *
     * @throws InvalidArgumentException when the value is not a valid
     *                                  decimal or has more than 3 fractional digits.
     */
    public function normalize(string|int $quantity): string
    {
        $q = trim((string) $quantity);
        if (! preg_match('/^\d+(\.\d+)?$/', $q)) {
            throw new InvalidArgumentException("Invalid quantity: {$quantity}");
        }
        if (str_contains($q, '.')) {
            $frac = rtrim(substr($q, strpos($q, '.') + 1), '0');
            if (strlen($frac) > self::SCALE) {
                throw new InvalidArgumentException("Quantity precision exceeds 3 decimal places: {$quantity}");
            }
            $q = bcadd($q, '0', self::SCALE);
            $q = rtrim(rtrim($q, '0'), '.');

            return $q === '' ? '0' : $q;
        }

        return bcadd($q, '0', 0);
    }

    /** Format with exactly 3 decimal places (storage form). */
    public function toStorage(string|int $quantity): string
    {
        return bcadd($this->normalize($quantity), '0', self::SCALE);
    }

    public function compare(string $a, string $b): int
    {
        return bccomp($this->normalize($a), $this->normalize($b), self::SCALE);
    }

    public function add(string $a, string $b): string
    {
        return $this->normalize(bcadd($this->normalize($a), $this->normalize($b), self::SCALE));
    }

    public function sub(string $a, string $b): string
    {
        return $this->normalize(bcsub($this->normalize($a), $this->normalize($b), self::SCALE));
    }

    /** True when $quantity is an exact multiple of $step (decimal-safe via thousandths). */
    public function isStepMultiple(string $quantity, string $step): bool
    {
        $q = $this->toThousandths($quantity);
        $s = $this->toThousandths($step);
        if ($s === '0') {
            return false;
        }

        return bcmod($q, $s) === '0';
    }

    /**
     * Validate a quantity against min / step / max rules.
     * Returns the normalized quantity string.
     *
     * @throws InvalidArgumentException with an internal English reason.
     */
    public function validate(string|int $quantity, string $min, string $step, ?string $max = null): string
    {
        $q = $this->normalize($quantity);
        if ($this->compare($q, $min) < 0) {
            throw new InvalidArgumentException("Quantity {$q} is below the minimum {$min}.");
        }
        if ($max !== null && $this->compare($q, $max) > 0) {
            throw new InvalidArgumentException("Quantity {$q} exceeds the maximum {$max}.");
        }
        // Steps are counted from zero: quantity must be a multiple of the step,
        // and (quantity - min) alignment is guaranteed when min itself is a
        // multiple of step, which admin validation enforces for products.
        if (! $this->isStepMultiple($q, $step)) {
            throw new InvalidArgumentException("Quantity {$q} is not a multiple of step {$step}.");
        }

        return $q;
    }

    /** Clamp a quantity down to available stock, respecting the step. */
    public function clampToStock(string $quantity, string $available, string $step): string
    {
        if ($this->compare($quantity, $available) <= 0) {
            return $this->normalize($quantity);
        }
        // Largest step multiple that fits in the available stock.
        $a = $this->toThousandths($available);
        $s = $this->toThousandths($step);
        if ($s === '0') {
            return '0';
        }
        $steps = bcdiv($a, $s, 0);
        $clamped = bcmul($steps, $s, 0);

        return $this->normalize(bcdiv($clamped, '1000', self::SCALE));
    }

    private function toThousandths(string $value): string
    {
        return bcmul($this->normalize($value), '1000', 0);
    }
}
