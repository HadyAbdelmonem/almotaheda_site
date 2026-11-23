<?php

namespace App\Filament\Resources\Customers;

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Customers (spec §7, Phase 8): read-only list of non-admin users
 * with their linked orders. No create / edit / delete from admin.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'عميل';

    protected static ?string $pluralModelLabel = 'العملاء';

    protected static string|UnitEnum|null $navigationGroup = 'الطلبات';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_admin', false);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات العميل')->schema([
                TextEntry::make('name')->label('الاسم'),
                TextEntry::make('phone')->label('الموبايل'),
                TextEntry::make('email')->label('البريد'),
                TextEntry::make('created_at')->label('تاريخ التسجيل')->dateTime('Y-m-d'),
            ])->columns(2),
            Section::make('طلبات العميل')->schema([
                RepeatableEntry::make('orders')->label('')
                    ->schema([
                        TextEntry::make('order_number')->label('رقم الطلب'),
                        TextEntry::make('status')->label('الحالة')
                            ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : (string) $state),
                        TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                        TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d'),
                    ])->columns(4),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('phone')->label('الموبايل')->searchable(),
                TextColumn::make('email')->label('البريد')->searchable(),
                TextColumn::make('orders_count')->label('عدد الطلبات')->counts('orders'),
                TextColumn::make('created_at')->label('التسجيل')->dateTime('Y-m-d')->sortable(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
