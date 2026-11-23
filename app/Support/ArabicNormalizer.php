<?php

namespace App\Support;

/**
 * Arabic search normalization (spec §8.4):
 * lowercase Latin, remove tashkeel and tatweel, أ إ آ -> ا, ة -> ه,
 * ى -> ي, Arabic-Indic digits -> Western digits, collapse whitespace.
 */
final class ArabicNormalizer
{
    public static function normalize(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = self::digitsToWestern($text);
        $text = mb_strtolower($text, 'UTF-8');

        // Remove tatweel and Arabic diacritics (tashkeel).
        $text = preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;

        $replacements = [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ؤ' => 'و',
            'ئ' => 'ي',
        ];
        $text = strtr($text, $replacements);

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Convert Arabic-Indic (٠-٩) and Eastern Arabic (۰-۹) digits to Western digits. */
    public static function digitsToWestern(string $text): string
    {
        $map = [];
        for ($i = 0; $i <= 9; $i++) {
            $map[mb_chr(0x0660 + $i)] = (string) $i; // ٠-٩
            $map[mb_chr(0x06F0 + $i)] = (string) $i; // ۰-۹
        }

        return strtr($text, $map);
    }

    /** Build a deterministic catalog identity hash (spec §5 products.catalog_identity). */
    public static function catalogIdentity(?string $modelCode, int|string|null $brandKey): string
    {
        $model = self::normalize($modelCode ?? '');
        $brand = $brandKey === null ? '' : self::normalize((string) $brandKey);

        return hash('sha256', $model.'|'.$brand);
    }
}
