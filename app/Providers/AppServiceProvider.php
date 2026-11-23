<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cache invalidation (spec §3): settings, category tree, brands
        // list and home sections are cached and must refresh on change.
        Setting::saved(fn (Setting $setting) => Cache::forget("setting.{$setting->key}"));
        Setting::deleted(fn (Setting $setting) => Cache::forget("setting.{$setting->key}"));

        $forgetCatalog = function () {
            Cache::forget('category_tree');
            Cache::forget('brands_list');
            Cache::forget('home_sections');
        };
        foreach ([Category::class, Brand::class, Product::class, Banner::class, Page::class] as $model) {
            $model::saved($forgetCatalog);
            $model::deleted($forgetCatalog);
        }
    }
}
