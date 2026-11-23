<?php

namespace App\Filament\Resources\Brands\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')
                    ->label('الشعار')
                    ->collection('logo'),
                TextColumn::make('name')
                    ->label('الماركة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('الرابط المختصر')
                    ->searchable(),
                TextColumn::make('products_count')
                    ->label('المنتجات')
                    ->counts('products'),
                IconColumn::make('is_authorized_dealer')
                    ->label('وكيل معتمد')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('مفعّلة')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('الترتيب'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('مفعّلة'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
