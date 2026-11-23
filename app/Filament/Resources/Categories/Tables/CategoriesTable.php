<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('التصنيف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('التصنيف الأب')
                    ->placeholder('رئيسي'),
                TextColumn::make('icon')
                    ->label('الأيقونة')
                    ->formatStateUsing(fn ($state, $record) => $state ? (config('icons')[$state] ?? $state) : ($record->parent?->icon ? (config('icons')[$record->parent->icon] ?? $record->parent->icon).' (موروثة)' : '-')),
                TextColumn::make('products_count')
                    ->label('المنتجات')
                    ->counts('products'),
                IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('الترتيب'),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('التصنيف الأب')
                    ->relationship('parent', 'name'),
                TernaryFilter::make('is_active')->label('مفعّل'),
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
