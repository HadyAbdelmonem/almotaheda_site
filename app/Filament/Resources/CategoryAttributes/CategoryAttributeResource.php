<?php

namespace App\Filament\Resources\CategoryAttributes;

use App\Filament\Resources\CategoryAttributes\Pages\CreateCategoryAttribute;
use App\Filament\Resources\CategoryAttributes\Pages\EditCategoryAttribute;
use App\Filament\Resources\CategoryAttributes\Pages\ListCategoryAttributes;
use App\Filament\Resources\CategoryAttributes\Schemas\CategoryAttributeForm;
use App\Filament\Resources\CategoryAttributes\Tables\CategoryAttributesTable;
use App\Models\CategoryAttribute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CategoryAttributeResource extends Resource
{
    protected static ?string $model = CategoryAttribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $modelLabel = 'خاصية فنية';

    protected static ?string $pluralModelLabel = 'الخصائص الفنية';

    protected static string|UnitEnum|null $navigationGroup = 'الكتالوج';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return CategoryAttributeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoryAttributesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoryAttributes::route('/'),
            'create' => CreateCategoryAttribute::route('/create'),
            'edit' => EditCategoryAttribute::route('/{record}/edit'),
        ];
    }
}
