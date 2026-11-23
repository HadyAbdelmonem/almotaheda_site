<?php

namespace App\Filament\Resources\WholesaleRequests;

use App\Filament\Resources\WholesaleRequests\Pages\EditWholesaleRequest;
use App\Filament\Resources\WholesaleRequests\Pages\ListWholesaleRequests;
use App\Models\WholesaleRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class WholesaleRequestResource extends Resource
{
    protected static ?string $model = WholesaleRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'طلب جملة';

    protected static ?string $pluralModelLabel = 'طلبات الجملة';

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 33;

    /** Status workflow labels (spec §5: new / contacted / closed). */
    public static function statusOptions(): array
    {
        return [
            'new' => 'جديد',
            'contacted' => 'تم التواصل',
            'closed' => 'مغلق',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->disabled(),
            TextInput::make('phone')->label('رقم الموبايل')->disabled(),
            TextInput::make('company')->label('الشركة')->disabled(),
            TextInput::make('monthly_volume')->label('حجم الشراء الشهري')->disabled(),
            Textarea::make('message')->label('الرسالة')->disabled()->columnSpanFull(),
            Select::make('status')->label('الحالة')->options(self::statusOptions())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('phone')->label('الموبايل')->searchable(),
                TextColumn::make('company')->label('الشركة'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'contacted' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(self::statusOptions()),
            ])
            ->recordActions([
                Action::make('setStatus')
                    ->label('تغيير الحالة')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->schema([
                        Select::make('status')->label('الحالة الجديدة')->options(self::statusOptions())->required(),
                    ])
                    ->action(fn (WholesaleRequest $record, array $data) => $record->update(['status' => $data['status']])),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWholesaleRequests::route('/'),
            'edit' => EditWholesaleRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
