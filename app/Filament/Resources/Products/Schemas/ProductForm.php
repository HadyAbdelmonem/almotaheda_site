<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Services\CatalogAttributeService;
use App\Services\QuantityService;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المنتج')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم المنتج')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('model_code')
                            ->label('كود الموديل')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $get('slug')) {
                                    $set('slug', Str::slug((string) $state) ?: 'product-'.substr(hash('sha256', (string) $state), 0, 8));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('الرابط المختصر')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('category_id')
                            ->label('التصنيف')
                            ->options(fn () => Category::query()->orderBy('sort_order')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('brand_id')
                            ->label('الماركة')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->nullable(),
                        TextInput::make('short_description')
                            ->label('وصف مختصر')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        RichEditor::make('description')
                            ->label('الوصف الكامل')
                            ->columnSpanFull(),
                    ]),

                Section::make('السعر والمخزون')
                    ->columns(3)
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('سعر التكلفة (داخلي فقط)')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('ج.م'),
                        TextInput::make('price')
                            ->label('سعر البيع')
                            ->numeric()
                            ->required()
                            ->prefix('ج.م'),
                        TextInput::make('sale_price')
                            ->label('سعر التخفيض')
                            ->numeric()
                            ->nullable()
                            ->prefix('ج.م'),
                        DateTimePicker::make('sale_ends_at')
                            ->label('انتهاء التخفيض')
                            ->nullable(),
                        TextInput::make('stock_quantity')
                            ->label('كمية المخزون')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                $step = (string) ($get('quantity_step') ?: '1');
                                try {
                                    app(QuantityService::class)->validate((string) $value, '0', $step);
                                } catch (\Throwable) {
                                    $fail("كمية المخزون يجب أن تكون من مضاعفات خطوة البيع ({$step}).");
                                }
                            }),
                        TextInput::make('low_stock_threshold')
                            ->label('حد انخفاض المخزون')
                            ->numeric()
                            ->nullable()
                            ->helperText('اتركه فارغاً لاستخدام الحد الافتراضي من الإعدادات.'),
                        Toggle::make('track_stock')
                            ->label('تتبع المخزون')
                            ->default(true),
                        Toggle::make('has_variants')
                            ->label('له متغيرات')
                            ->helperText('أضف المتغيرات من تبويب المتغيرات بعد الحفظ.'),
                        Toggle::make('is_active')
                            ->label('مفعّل')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('مميز'),
                    ]),

                Section::make('وحدة البيع والكميات')
                    ->columns(4)
                    ->schema([
                        Select::make('unit_code')
                            ->label('وحدة البيع')
                            ->options(fn () => collect(config('units'))->mapWithKeys(fn ($unit, $key) => [$key => $unit['label_ar']])->all())
                            ->required()
                            ->default('piece')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('quantity_step', (string) config("units.{$state}.default_step", '1'));
                                $set('min_order_quantity', (string) config("units.{$state}.default_min", '1'));
                            }),
                        TextInput::make('min_order_quantity')
                            ->label('الحد الأدنى للطلب')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                try {
                                    app(QuantityService::class)->validate(
                                        (string) $value,
                                        (string) $value,
                                        (string) ($get('quantity_step') ?: '1'),
                                        ($get('max_order_quantity') ?? null) ?: null
                                    );
                                } catch (\Throwable $e) {
                                    $fail('كمية غير صالحة: '.$e->getMessage());
                                }
                            }),
                        TextInput::make('quantity_step')
                            ->label('خطوة الكمية')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->helperText('قطعة = 1، متر قد تكون 0.25 أو 0.5.'),
                        TextInput::make('max_order_quantity')
                            ->label('الحد الأقصى للطلب')
                            ->numeric()
                            ->nullable(),
                    ]),

                Section::make('المواصفات الوصفية')
                    ->schema([
                        Repeater::make('specs')
                            ->label('مواصفات إضافية (غير قابلة للفلترة)')
                            ->schema([
                                TextInput::make('label')->label('الاسم')->required(),
                                TextInput::make('value')->label('القيمة')->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0),
                    ]),

                Section::make('الخصائص الفنية')
                    ->schema([
                        Repeater::make('attributeValues')
                            ->label('قيم الخصائص الفنية')
                            ->relationship()
                            ->schema([
                                Select::make('category_attribute_id')
                                    ->label('الخاصية')
                                    ->options(function (Get $get) {
                                        $categoryId = $get('../../category_id');
                                        if (! $categoryId || ! ($category = Category::find($categoryId))) {
                                            return [];
                                        }

                                        return app(CatalogAttributeService::class)->forCategory($category)
                                            ->mapWithKeys(fn ($attr) => [$attr->id => $attr->name_ar.($attr->unit_label ? " ({$attr->unit_label})" : '')])
                                            ->all();
                                    })
                                    ->required()
                                    ->live(),
                                TextInput::make('value_text')
                                    ->label('القيمة (نص / اختيار)')
                                    ->visible(fn (Get $get) => ($attr = CategoryAttribute::find($get('category_attribute_id'))) && in_array($attr->data_type, ['text', 'select'], true)),
                                TextInput::make('value_number')
                                    ->label('القيمة (رقم)')
                                    ->numeric()
                                    ->visible(fn (Get $get) => ($attr = CategoryAttribute::find($get('category_attribute_id'))) && $attr->data_type === 'number'),
                                Toggle::make('value_boolean')
                                    ->label('القيمة (نعم / لا)')
                                    ->visible(fn (Get $get) => ($attr = CategoryAttribute::find($get('category_attribute_id'))) && $attr->data_type === 'boolean'),
                                TextInput::make('value_label_snapshot')
                                    ->label('اسم العرض (للقوائم)')
                                    ->nullable(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->helperText('اختر التصنيف أولاً لتظهر خصائصه الموروثة. عبّئ عمود قيمة واحداً فقط حسب نوع الخاصية.'),
                    ]),

                Section::make('الصور')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('صور المنتج')
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxSize(4096)
                            ->helperText('أول صورة هي الرئيسية. تُحوّل تلقائياً إلى WebP بمقاسات 400/800/1200. نص بديل الصورة يرجع لاسم المنتج عند العرض.'),
                    ]),

                Section::make('SEO')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')->label('عنوان SEO')->maxLength(255),
                        Textarea::make('meta_description')->label('وصف SEO')->rows(2),
                    ]),
            ]);
    }
}
