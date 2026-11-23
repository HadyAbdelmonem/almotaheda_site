<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceTiersRelationManager extends RelationManager
{
    protected static string $relationship = 'priceTiers';

    protected static ?string $title = 'شرائح الأسعار';

    protected static ?string $modelLabel = 'شريحة سعر';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('min_qty')
                    ->label('الحد الأدنى للكمية')
                    ->numeric()
                    ->required()
                    ->helperText('بوحدة بيع المنتج ومتوافقة مع خطوة الكمية.'),
                TextInput::make('discount_percent')
                    ->label('نسبة الخصم %')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(90)
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('min_qty')
            ->defaultSort('min_qty')
            ->columns([
                TextColumn::make('min_qty')->label('من كمية'),
                TextColumn::make('discount_percent')->label('الخصم %')->suffix('%'),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
