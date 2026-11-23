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
            // Only the ID tree is cached (see HomeController): caching
            // Eloquent collections can unserialize as incomplete classes
            // on some PHP/OPcache setups. Rows are re-hydrated in order.
            $tree = Cache::rememberForever('category_tree', function () {
                return Category::query()
                    ->whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                    ->get()
                    ->map(fn (Category $parent) => [
                        'id' => $parent->id,
                        'children' => $parent->children->pluck('id')->all(),
                    ])
                    ->all();
            });

            $parentIds = array_column($tree, 'id');
            $childIds = array_merge(...array_map(fn ($node) => $node['children'], $tree) ?: [[]]);
            $byId = Category::query()
                ->whereIn('id', [...$parentIds, ...$childIds])
                ->get()
                ->keyBy('id');

            return collect($tree)
                ->map(function ($node) use ($byId) {
                    $parent = $byId->get($node['id']);
                    if ($parent === null) {
                        return null;
                    }
                    $parent->setRelation('children', collect($node['children'])
                        ->map(fn ($id) => $byId->get($id))
                        ->filter()
                        ->values());

                    return $parent;
                })
                ->filter()
                ->values();
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
