<?php

namespace App\Support;

/**
 * Decimal-string money arithmetic backed by BCMath (spec §3, §8.2).
 * Money values are always strings with 2 decimal places; binary floats
 * are never used as a source of truth. Rounding is half-up.
 */
final class Money
{
    public const SCALE = 2;

    /** Normalize any numeric input to a canonical decimal string at the given scale (half-up). */
    public static function round(string|int $value, int $scale = self::SCALE): string
    {
        $value = self::toDecimalString($value);
        $negative = str_starts_with($value, '-');
        $abs = $negative ? substr($value, 1) : $value;
        $increment = $scale === 0 ? '0.5' : '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($abs, $increment, $scale);
        $result = $negative && bccomp($rounded, '0', $scale) !== 0 ? '-'.$rounded : $rounded;

        return self::normalize($result, $scale);
    }

    public static function of(string|int $value): string
    {
        return self::round($value, self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return self::round(bcadd(self::toDecimalString($a), self::toDecimalString($b), self::SCALE + 4), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return self::round(bcsub(self::toDecimalString($a), self::toDecimalString($b), self::SCALE + 4), self::SCALE);
    }

    /** Multiply a money amount by a decimal quantity (scale 3) and round half-up to 2dp. */
    public static function mulQty(string $amount, string $quantity): string
    {
        $raw = bcmul(self::toDecimalString($amount), self::toDecimalString($quantity), 6);

        return self::round($raw, self::SCALE);
    }

    /** Percentage of an amount: round(amount * percent / 100) half-up to 2dp. */
    public static function percent(string $amount, string $percent): string
    {
        $raw = bcdiv(bcmul(self::toDecimalString($amount), self::toDecimalString($percent), 6), '100', 6);

        return self::round($raw, self::SCALE);
    }

    /** Apply a percentage discount and return the discounted amount. */
    public static function discount(string $amount, string $percent): string
    {
        return self::sub($amount, self::percent($amount, $percent));
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::toDecimalString($a), self::toDecimalString($b), 4);
    }

    public static function min(string $a, string $b): string
    {
        return self::compare($a, $b) <= 0 ? self::of($a) : self::of($b);
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? self::of($a) : self::of($b);
    }

    public static function isZero(string $a): bool
    {
        return self::compare($a, '0') === 0;
    }

    /**
     * Format for display (spec §8.2): Western digits, thousands separator,
     * no decimals when whole, otherwise 2 decimals, suffixed with ج.م.
     * Example: "1,250 ج.م" / "1,250.50 ج.م".
     */
    public static function format(string|int $value): string
    {
        $v = self::of((string) $value);
        $negative = str_starts_with($v, '-');
        $abs = $negative ? substr($v, 1) : $v;
        [$int, $frac] = array_pad(explode('.', $abs), 2, '00');
        $grouped = strrev(implode(',', str_split(strrev($int), 3)));
        $out = $frac === '00' ? $grouped : $grouped.'.'.rtrim($frac, '0');

        return ($negative ? '-' : '').$out.' ج.م';
    }

    private static function toDecimalString(string|int $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new \InvalidArgumentException("Invalid decimal value: {$value}");
        }

        return $value;
    }

    private static function normalize(string $value, int $scale): string
    {
        // bcmath already returns fixed-scale strings; just guard the negative-zero case.
        if (bccomp($value, '0', $scale) === 0) {
            return $scale === 0 ? '0' : '0.'.str_repeat('0', $scale);
        }

        return $value;
    }
}
