<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/** Six editable static pages (spec §5) with Arabic placeholder text. */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['about', 'من نحن', 'متخصصون في توريد جميع المستلزمات الكهربائية — صفحة تعريفية بالمتجر، قابلة للتعديل من لوحة التحكم.'],
            ['shipping-policy', 'سياسة الشحن', 'معلومات الشحن والتوصيل لكل المحافظات — قابل للتعديل من لوحة التحكم.'],
            ['return-policy', 'سياسة الاسترجاع', 'يمكن الاسترجاع خلال 7 أيام من الاستلام بشرط سلامة المنتج — قابل للتعديل من لوحة التحكم.'],
            ['faq', 'الأسئلة الشائعة', 'إجابات على الأسئلة الأكثر تكرارًا — قابل للتعديل من لوحة التحكم.'],
            ['terms', 'الشروط والأحكام', 'شروط استخدام المتجر والشراء — قابل للتعديل من لوحة التحكم.'],
            ['privacy', 'سياسة الخصوصية', 'كيفية التعامل مع بيانات العملاء — قابل للتعديل من لوحة التحكم.'],
        ];

        foreach ($pages as [$slug, $title, $content]) {
            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_active' => true]);
        }
    }
}
