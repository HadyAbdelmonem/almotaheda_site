<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safely purge demo data (spec Phase 11): demo products (with their
 * variants, tiers, attribute values, reviews, wishlists and media),
 * all orders and their dependent rows (items, histories, stock
 * movements, payment transactions) and demo coupons.
 *
 * KEPT: settings, shipping rates, categories, category attributes,
 * brands, static pages and users — the production structure.
 *
 * Environment guard: runs freely only in local/testing. Anywhere
 * else it requires --force AND an interactive confirmation, so it
 * can never wipe a production database by accident.
 */
class PurgeDemoData extends Command
{
    protected $signature = 'store:purge-demo {--force : Allow running outside local/testing (still asks for confirmation)}';

    protected $description = 'Remove demo products, orders and coupons while keeping store structure and settings';

    public function handle(): int
    {
        $env = app()->environment();
        if (! in_array($env, ['local', 'testing'], true)) {
            if (! $this->option('force')) {
                $this->error("Refusing to purge demo data in the '{$env}' environment without --force.");

                return self::FAILURE;
            }
            if (! $this->confirm('You are about to DELETE all products, orders and coupons in a non-local environment. Continue?')) {
                $this->warn('Aborted. Nothing was deleted.');

                return self::FAILURE;
            }
        }

        $counts = DB::transaction(function () {
            $orders = Order::count();
            // Dependent rows first (FK order), then the catalog rows.
            DB::table('payment_transactions')->delete();
            DB::table('order_status_histories')->delete();
            DB::table('stock_movements')->delete();
            DB::table('order_items')->delete();
            Order::query()->delete();

            $products = Product::withTrashed()->count();
            DB::table('reviews')->delete();
            DB::table('wishlists')->delete();
            DB::table('product_attribute_values')->delete();
            DB::table('product_price_tiers')->delete();
            DB::table('product_variants')->delete();
            // forceDelete so Spatie media files are cleaned up too.
            Product::withTrashed()->get()->each->forceDelete();

            $coupons = Coupon::count();
            Coupon::query()->delete();

            return compact('orders', 'products', 'coupons');
        });

        $this->info("Purged demo data: {$counts['products']} products, {$counts['orders']} orders, {$counts['coupons']} coupons.");
        $this->info('Kept: settings, shipping rates, categories, attributes, brands, pages, users.');

        return self::SUCCESS;
    }
}
