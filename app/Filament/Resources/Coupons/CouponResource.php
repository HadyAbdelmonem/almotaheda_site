<?php

namespace App\Filament\Resources\Coupons;

use App\Enums\CouponType;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use App\Services\CouponService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Coupons admin (spec §4.3/§7, Phase 8). `used_count` is read-only:
 * it moves only inside order transactions (increment on creation,
 * decrement once on cancellation — see OrderService).
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $modelLabel = 'كوبون';

    protected static ?string $pluralModelLabel = 'الكوبونات';

    protected static string|UnitEnum|null $navigationGroup = 'التسويق';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('الكود')->required()->maxLength(50)
                ->mutateStateForValidationUsing(fn ($state) => app(CouponService::class)->normalizeCode((string) $state))
                ->helperText('يُحفظ بالأحرف الكبيرة؛ المقارنة غير حساسة لحالة الأحرف.'),
            Select::make('type')->label('النوع')
                ->options(collect(CouponType::cases())->mapWithKeys(fn (CouponType $t) => [$t->value => $t->label()])->all())
                ->required(),
            TextInput::make('value')->label('القيمة')->numeric()->minValue(0)->required()
                ->helperText('للنسبة: من 0 إلى 100. للمبلغ الثابت: بالجنيه.'),
            TextInput::make('min_order')->label('الحد الأدنى للطلب (ج.م)')->numeric()->minValue(0)->default(0),
            TextInput::make('max_discount')->label('أقصى خصم (ج.م) — اختياري')->numeric()->minValue(0),
            TextInput::make('usage_limit')->label('حد الاستخدام — فارغ = غير محدود')->numeric()->minValue(1),
            TextInput::make('used_count')->label('عدد مرات الاستخدام')->disabled()->dehydrated(false),
            DateTimePicker::make('starts_at')->label('يبدأ في'),
            DateTimePicker::make('expires_at')->label('ينتهي في')->after('starts_at'),
            Toggle::make('is_active')->label('مفعّل')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الكود')->searchable(),
                TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn (CouponType $state) => $state->label())
                    ->color(fn (CouponType $state) => $state->color()),
                TextColumn::make('value')->label('القيمة'),
                TextColumn::make('min_order')->label('حد أدنى')->money('EGP'),
                TextColumn::make('used_count')->label('استُخدم'),
                TextColumn::make('usage_limit')->label('الحد')->placeholder('∞'),
                TextColumn::make('expires_at')->label('ينتهي')->dateTime('Y-m-d'),
                IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
