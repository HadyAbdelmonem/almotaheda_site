<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached storefront navigation data (spec §3): the category tree and
 * the active static pages. Invalidated by model observers in
 * AppServiceProvider (the `category_tree` / catalog cache keys).
 */
class StorefrontNav
{
    /** @return Collection<int, Category> parent categories with active children eager-loaded. */
    public static function categories(): Collection
    {
        try {
            return Cache::rememberForever('category_tree', function () {
                return Category::query()
                    ->whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                    ->get();
            });
        } catch (\Throwable) {
            // Catalog tables may not exist yet (e.g. during initial setup).
            return collect();
        }
    }

    /** @return Collection<int, Page> */
    public static function pages(): Collection
    {
        try {
            return Page::query()->where('is_active', true)->orderBy('title')->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Topbar messages from settings: a plain string or a list of strings. */
    public static function topbarMessages(): array
    {
        $raw = setting('topbar_text', '');
        if (is_array($raw)) {
            return array_values(array_filter(array_map(fn ($m) => trim((string) $m), $raw), fn ($m) => $m !== ''));
        }

        return trim((string) $raw) === '' ? [] : [trim((string) $raw)];
    }
}
