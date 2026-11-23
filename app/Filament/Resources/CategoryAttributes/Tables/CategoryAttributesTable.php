<?php

namespace App\Filament\Resources\CategoryAttributes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoryAttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->searchable(),
                TextColumn::make('name_ar')
                    ->label('الخاصية')
                    ->searchable(),
                TextColumn::make('key')
                    ->label('المفتاح')
                    ->searchable(),
                TextColumn::make('data_type')
                    ->label('النوع')
                    ->formatStateUsing(fn ($state) => ['text' => 'نص', 'number' => 'رقم', 'select' => 'قائمة', 'boolean' => 'نعم/لا'][$state] ?? $state),
                TextColumn::make('unit_label')
                    ->label('الوحدة')
                    ->placeholder('-'),
                IconColumn::make('is_filterable')
                    ->label('قابلة للفلترة')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('مفعّلة')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name'),
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
