<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * Read a store setting (spec §5 settings table) with cache.
     * Values are stored as JSON in the settings table.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            $value = Cache::rememberForever("setting.{$key}", function () use ($key) {
                $row = Setting::query()->where('key', $key)->first();

                return $row ? $row->value : '__missing__';
            });
        } catch (Throwable) {
            // Settings table may not exist yet (e.g. during initial migration).
            return $default;
        }

        return $value === '__missing__' || $value === null ? $default : $value;
    }
}

if (! function_exists('store_logo_url')) {
    /**
     * Store logo URL: the owner-configured `logo` setting (URL or public
     * path) when set, otherwise the designed default mark shipped in
     * public/branding/logo.webp (yellow bolt+plug on ink, §10.2 tokens).
     */
    function store_logo_url(): string
    {
        $logo = trim((string) setting('logo', ''));
        if ($logo === '') {
            return asset('branding/logo.webp');
        }

        return str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')
            ? $logo
            : asset($logo);
    }
}

if (! function_exists('unit_label')) {
    /** Arabic label for a sale-unit key from config/units.php (spec §4.11). */
    function unit_label(string $unitCode): string
    {
        return config("units.{$unitCode}.label_ar", $unitCode);
    }
}
