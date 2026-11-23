<?php

namespace App\Filament\Resources\CategoryAttributes\Schemas;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Services\CatalogAttributeService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CategoryAttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('التصنيف')
                    ->options(fn () => Category::query()->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get) => ($catId = $get('category_id')) && ($cat = Category::find($catId)) && $cat->parent_id
                        ? 'الخصائص الموروثة من الأب (لا يمكن إعادة تعريفها): '.implode('، ', app(CatalogAttributeService::class)->inheritedKeys($cat))
                        : 'خصائص التصنيف الأب تُورث تلقائياً للتصنيفات الفرعية.'),
                TextInput::make('key')
                    ->label('المفتاح (إنجليزي ثابت)')
                    ->required()
                    ->maxLength(255)
                    ->unique(table: CategoryAttribute::class, column: 'key', ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('category_id', $get('category_id')))
                    ->helperText('مثال: current_rating_a — لا يتغير بعد إدخال منتجات.'),
                TextInput::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->required()
                    ->maxLength(255),
                Select::make('data_type')
                    ->label('نوع البيانات')
                    ->options([
                        'text' => 'نص',
                        'number' => 'رقم',
                        'select' => 'اختيار من قائمة',
                        'boolean' => 'نعم / لا',
                    ])
                    ->required()
                    ->live()
                    ->default('text'),
                TextInput::make('unit_label')
                    ->label('وحدة القياس')
                    ->maxLength(50)
                    ->helperText('مثال: A أو V أو W أو mm² — تظهر بجانب القيمة.'),
                Repeater::make('options')
                    ->label('خيارات القائمة')
                    ->schema([
                        TextInput::make('value')->label('القيمة المخزنة')->required(),
                        TextInput::make('label')->label('الاسم بالعربية')->required(),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('data_type') === 'select'),
                Toggle::make('is_filterable')
                    ->label('تظهر في فلاتر المتجر'),
                Toggle::make('is_required')
                    ->label('إلزامية للمنتج'),
                Toggle::make('is_active')
                    ->label('مفعّلة')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('ترتيب العرض')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
