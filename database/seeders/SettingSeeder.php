<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/** Default settings (spec §4.1). All values are editable in admin.
 * The store identity values below are the owner's real public data
 * (provided 2026-11-23): store name, WhatsApp/phone, email, bank
 * transfer instructions and the Facebook page. They are public
 * contact data, not secrets. Free-shipping threshold (3000),
 * delivery estimate and 7-day returns keep the spec defaults. */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'free_shipping_threshold' => 3000,
            'delivery_estimate_text' => 'التوصيل خلال 2–5 أيام عمل',
            'return_policy_days' => 7,
            'topbar_text' => 'شحن مجاني فوق 3,000 ج.م — توصيل 2–5 أيام',
            'whatsapp_number' => '201033808633',
            'phones' => ['01033808633'],
            'bank_transfer_details' => 'التحويل عبر InstaPay أو فودافون كاش على الرقم 01033808633',
            'payment_cod_enabled' => true,
            'payment_bank_enabled' => true,
            'payment_card_enabled' => false,
            'low_stock_default' => 5,
            'why_us_blocks' => [
                ['icon' => 'badge-check', 'title' => 'أصلي 100%', 'text' => 'منتجات أصلية من وكلاء معتمدين'],
                ['icon' => 'gauge', 'title' => 'فحص قبل الشحن', 'text' => 'بنفحص كل طلب قبل ما يطلع من المخزن'],
                ['icon' => 'shield-check', 'title' => 'ضمان', 'text' => 'ضمان معتمد على منتجات مختارة'],
                ['icon' => 'receipt', 'title' => 'فاتورة رسمية', 'text' => 'فاتورة شراء مع كل طلب'],
            ],
            'store_name' => 'شركة الصفا للتوريدات الكهربائية',
            'logo' => '',
            'address' => 'التجمع الخامس، القاهرة',
            'map_embed' => '',
            'email' => 'hadyabdelmonem11@gmail.com',
            'social_links' => ['facebook' => 'https://facebook.com/people/%D8%B4%D8%B1%D9%83%D8%A9-%D8%A7%D9%84%D8%B5%D9%81%D8%A7-%D9%84%D9%84%D8%AA%D9%88%D8%B1%D9%8A%D8%AF%D8%A7%D8%AA-%D8%A7%D9%84%D9%83%D9%87%D8%B1%D8%A8%D8%A7%D8%A6%D9%8A%D8%A9/61570780436326/'],
            'tax_number' => '',
            'motion_level' => 'full',
            'ga4_measurement_id' => '',
            'search_console_verification' => '',
            'store_tax_note' => '',
            'catalog_filter_max_options' => 12,
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            Cache::forget("setting.{$key}");
        }
    }
}
