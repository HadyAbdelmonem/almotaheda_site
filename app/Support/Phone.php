<?php

namespace App\Support;

/**
 * Egyptian phone handling (spec §8.1).
 * Accept Arabic-Indic digits, strip spaces/dashes and +20 / 0020 prefixes.
 * A customer mobile must match ^01[0125][0-9]{8}$.
 */
final class Phone
{
    public static function normalize(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        $phone = ArabicNormalizer::digitsToWestern($phone);
        $phone = preg_replace('/[\s\-()]+/', '', $phone) ?? '';

        if (str_starts_with($phone, '+20')) {
            $phone = '0'.substr($phone, 3);
        } elseif (str_starts_with($phone, '0020')) {
            $phone = '0'.substr($phone, 4);
        } elseif (str_starts_with($phone, '+2')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '002')) {
            $phone = substr($phone, 2);
        }

        // Bare international form without prefix markers: 20 + 10-digit local number.
        if (preg_match('/^20\d{10}$/', $phone)) {
            $phone = '0'.substr($phone, 2);
        }

        return $phone;
    }

    public static function isValidMobile(?string $phone): bool
    {
        return (bool) preg_match('/^01[0125][0-9]{8}$/', self::normalize($phone));
    }

    /** WhatsApp international form: 20 + number without the leading zero. */
    public static function toWhatsApp(?string $phone): string
    {
        $normalized = self::normalize($phone);

        return '20'.ltrim($normalized, '0');
    }
}
