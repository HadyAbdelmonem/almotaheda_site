<?php

namespace App\Filament\Resources\Banners;

use App\Enums\BannerPosition;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Banners admin (spec §5/§7, Phase 8): hero slider + promo tiles,
 * desktop and mobile images with previews, scheduling and ordering.
 */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'بانر';

    protected static ?string $pluralModelLabel = 'البانرات';

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('العنوان')->required()->maxLength(255),
            Select::make('position')->label('الموضع')
                ->options(collect(BannerPosition::cases())->mapWithKeys(fn (BannerPosition $p) => [$p->value => $p->label()])->all())
                ->required(),
            FileUpload::make('image_desktop')->label('صورة سطح المكتب')
                ->image()->maxSize(4096)->disk('public')->directory('banners')->visibility('public')
                ->imagePreviewHeight('120'),
            FileUpload::make('image_mobile')->label('صورة الموبايل')
                ->image()->maxSize(4096)->disk('public')->directory('banners')->visibility('public')
                ->imagePreviewHeight('120'),
            TextInput::make('link_url')->label('رابط عند الضغط')->maxLength(500),
            TextInput::make('sort_order')->label('الترتيب')->numeric()->minValue(0)->default(0),
            DateTimePicker::make('starts_at')->label('يبدأ في'),
            DateTimePicker::make('ends_at')->label('ينتهي في')->after('starts_at'),
            Toggle::make('is_active')->label('مفعّل')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_desktop')->label('الصورة')->disk('public'),
                TextColumn::make('title')->label('العنوان')->searchable(),
                TextColumn::make('position')->label('الموضع')->badge()
                    ->formatStateUsing(fn (BannerPosition $state) => $state->label())
                    ->color(fn (BannerPosition $state) => $state->color()),
                TextColumn::make('sort_order')->label('الترتيب'),
                TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d'),
                TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d'),
                IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
