<?php

namespace App\Http\Controllers;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Product;
use App\Support\Seo;
use App\Support\StorefrontNav;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Homepage data (spec §6.5/§10.6). Everything is database-driven and
 * cached under `home_sections`; model observers in AppServiceProvider
 * invalidate the key when catalog/banner data changes. Empty sections
 * are hidden by the view — nothing is invented.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        try {
            // Only IDs are cached: caching Eloquent collections serializes
            // whole model graphs into the file cache, which can come back
            // as __PHP_Incomplete_Class on some PHP/OPcache setups (seen on
            // PHP 8.5/XAMPP). IDs serialize safely; rows are re-hydrated in
            // the cached order with fresh queries.
            $ids = Cache::rememberForever('home_sections', function () {
                $bannerIds = fn (BannerPosition $position, int $limit) => Banner::query()
                    ->where('position', $position)
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                    ->orderBy('sort_order')
                    ->limit($limit)
                    ->pluck('id')
                    ->all();

                $productIds = fn ($order) => $order(Product::query()->where('is_active', true))
                    ->limit(10)
                    ->pluck('id')
                    ->all();

                // Best sellers (owner fix, 2026-11-27): products with REAL
                // sales lead, ordered by sales_count; the row is then
                // filled up to 10 with the newest active products so the
                // carousel is never a single lonely card on a young store
                // with little sales history. As real orders accumulate,
                // actual best sellers take the row over automatically.
                $bestSellerIds = Product::query()->where('is_active', true)
                    ->where('sales_count', '>', 0)
                    ->orderByDesc('sales_count')->orderByDesc('id')
                    ->limit(10)
                    ->pluck('id')
                    ->all();
                if (count($bestSellerIds) < 10) {
                    $fillIds = Product::query()->where('is_active', true)
                        ->whereNotIn('id', $bestSellerIds)
                        ->orderByDesc('created_at')->orderByDesc('id')
                        ->limit(10 - count($bestSellerIds))
                        ->pluck('id')
                        ->all();
                    $bestSellerIds = [...$bestSellerIds, ...$fillIds];
                }

                return [
                    'heroBanners' => $bannerIds(BannerPosition::Hero, 5),
                    'promoBanners' => $bannerIds(BannerPosition::Promo, 3),
                    'brands' => Brand::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->all(),
                    'bestSellers' => $bestSellerIds,
                    'newArrivals' => $productIds(fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')),
                ];
            });

            $inOrder = fn ($models, array $orderedIds) => collect($orderedIds)
                ->map(fn ($id) => $models->get($id))
                ->filter()
                ->values();

            $bannerById = Banner::query()->whereIn('id', [...$ids['heroBanners'], ...$ids['promoBanners']])->get()->keyBy('id');
            $brandById = Brand::query()->whereIn('id', $ids['brands'])->with('media')->get()->keyBy('id');
            $productById = Product::query()
                ->whereIn('id', [...$ids['bestSellers'], ...$ids['newArrivals']])
                ->with(['brand', 'category.parent', 'media', 'variants' => fn ($q) => $q->where('is_active', true), 'priceTiers'])
                ->get()
                ->keyBy('id');

            $data = [
                'heroBanners' => $inOrder($bannerById, $ids['heroBanners']),
                'promoBanners' => $inOrder($bannerById, $ids['promoBanners']),
                'brands' => $inOrder($brandById, $ids['brands']),
                'bestSellers' => $inOrder($productById, $ids['bestSellers']),
                'newArrivals' => $inOrder($productById, $ids['newArrivals']),
            ];
        } catch (\Throwable) {
            // Content tables may not exist yet (e.g. during initial setup).
            $data = [
                'heroBanners' => collect(),
                'promoBanners' => collect(),
                'brands' => collect(),
                'bestSellers' => collect(),
                'newArrivals' => collect(),
            ];
        }

        return view('home', array_merge($data, [
            'categories' => StorefrontNav::categories(),
            'title' => trim((string) setting('store_name', '')) ?: __('store.name'),
            'metaDescription' => (trim((string) setting('store_name', '')) ?: __('store.name')).' — '.__('store.tagline'),
            'canonical' => url('/'),
            'jsonLd' => [Seo::organizationJsonLd()],
        ]));
    }
}
