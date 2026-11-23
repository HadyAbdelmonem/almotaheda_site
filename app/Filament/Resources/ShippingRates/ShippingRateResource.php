<?php

namespace App\Filament\Resources\ShippingRates;

use App\Filament\Resources\ShippingRates\Pages\EditShippingRate;
use App\Filament\Resources\ShippingRates\Pages\ListShippingRates;
use App\Models\ShippingRate;
use BackedEnum;
use Filament\Actions\EditAction;
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
 * Shipping rates for the 27 governorates (spec §4.4/§7, Phase 8).
 * Seeded fees are PLACEHOLDERS — the owner replaces them here; the
 * storefront and checkout read only active rows.
 */
class ShippingRateResource extends Resource
{
    protected static ?string $model = ShippingRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'محافظة شحن';

    protected static ?string $pluralModelLabel = 'أسعار الشحن';

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 51;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')->label('المحافظة')->disabled()->dehydrated(false),
            TextInput::make('fee')->label('رسوم الشحن (ج.م)')->numeric()->minValue(0)->required()
                ->helperText('الأسعار الافتراضية تجريبية — عدّلها لأسعارك الحقيقية.'),
            TextInput::make('est_days_min')->label('أقل مدة توصيل (أيام)')->numeric()->minValue(0)->required(),
            TextInput::make('est_days_max')->label('أقصى مدة توصيل (أيام)')->numeric()->minValue(0),
            TextInput::make('sort_order')->label('الترتيب')->numeric()->minValue(0),
            Toggle::make('is_active')->label('متاحة عند إتمام الطلب'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name_ar')->label('المحافظة')->searchable(),
                TextColumn::make('fee')->label('الرسوم')->money('EGP'),
                TextColumn::make('est_days_min')->label('من (أيام)'),
                TextColumn::make('est_days_max')->label('إلى (أيام)'),
                IconColumn::make('is_active')->label('متاحة')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingRates::route('/'),
            'edit' => EditShippingRate::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // The 27 governorates are fixed; only editing is allowed.
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
