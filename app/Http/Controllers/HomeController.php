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
            $data = Cache::rememberForever('home_sections', function () {
                $bannerQuery = fn (BannerPosition $position, int $limit) => Banner::query()
                    ->where('position', $position)
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                    ->orderBy('sort_order')
                    ->limit($limit)
                    ->get();

                $productQuery = fn () => Product::query()
                    ->where('is_active', true)
                    ->with(['brand', 'category.parent', 'media', 'variants' => fn ($q) => $q->where('is_active', true), 'priceTiers']);

                return [
                    'heroBanners' => $bannerQuery(BannerPosition::Hero, 5),
                    'promoBanners' => $bannerQuery(BannerPosition::Promo, 3),
                    'brands' => Brand::query()->where('is_active', true)->orderBy('sort_order')->with('media')->get(),
                    'bestSellers' => $productQuery()->orderByDesc('sales_count')->orderByDesc('id')->limit(10)->get(),
                    'newArrivals' => $productQuery()->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get(),
                ];
            });
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
