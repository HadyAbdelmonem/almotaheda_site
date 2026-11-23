<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * Dynamic sitemap (spec §9): home, active categories, active brands,
 * active products and active static pages only. The local-only /_ui
 * kit, admin, cart, checkout and account URLs are never included.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];
        $add = function (string $loc, ?string $lastmod = null) use (&$urls) {
            $urls[] = ['loc' => $loc, 'lastmod' => $lastmod];
        };

        $add(url('/'), now()->toDateString());

        foreach (Category::query()->where('is_active', true)->orderBy('sort_order')->get() as $category) {
            $add(route('categories.show', $category->slug), $category->updated_at?->toDateString());
        }
        foreach (Brand::query()->where('is_active', true)->orderBy('sort_order')->get() as $brand) {
            $add(route('brands.show', $brand->slug), $brand->updated_at?->toDateString());
        }
        foreach (Product::query()->where('is_active', true)->orderBy('id')->get(['slug', 'updated_at']) as $product) {
            $add(route('products.show', $product->slug), $product->updated_at?->toDateString());
        }
        foreach (Page::query()->where('is_active', true)->get() as $page) {
            $add(route('pages.show', $page->slug), $page->updated_at?->toDateString());
        }
        $add(route('contact'));
        $add(route('wholesale'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>';
            if ($url['lastmod']) {
                $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        // Search/filter query URLs are handled with noindex meta and
        // must NOT be blocked here (spec §9), so only the private
        // areas are disallowed.
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /account',
            'Disallow: /login',
            'Disallow: /register',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
