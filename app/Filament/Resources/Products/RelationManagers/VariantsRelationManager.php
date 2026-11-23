<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'المتغيرات';

    protected static ?string $modelLabel = 'متغير';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم المتغير')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                KeyValue::make('options')
                    ->label('الخيارات')
                    ->keyLabel('الخاصية')
                    ->valueLabel('القيمة')
                    ->helperText('مثال: اللون = أبيض.'),
                TextInput::make('price')
                    ->label('سعر خاص بالمتغير')
                    ->numeric()
                    ->nullable()
                    ->prefix('ج.م')
                    ->helperText('اتركه فارغاً ليرث سعر المنتج.'),
                TextInput::make('sale_price')
                    ->label('تخفيض المتغير')
                    ->numeric()
                    ->nullable()
                    ->prefix('ج.م'),
                DateTimePicker::make('sale_ends_at')
                    ->label('انتهاء تخفيض المتغير')
                    ->nullable()
                    ->helperText('ينطبق على تخفيض المتغير فقط، لا على تخفيض المنتج.'),
                TextInput::make('cost_price')
                    ->label('تكلفة المتغير (داخلي)')
                    ->numeric()
                    ->nullable(),
                TextInput::make('stock_quantity')
                    ->label('مخزون المتغير')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->helperText('بنفس وحدة بيع المنتج، وقد يكون كسرياً.'),
                TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('المتغير'),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('price')->label('السعر')->money('EGP')->placeholder('يرث المنتج'),
                TextColumn::make('stock_quantity')->label('المخزون'),
                IconColumn::make('is_active')->label('مفعّل')->boolean(),
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
