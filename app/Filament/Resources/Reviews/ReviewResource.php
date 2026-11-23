<?php

namespace App\Filament\Resources\Reviews;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Review moderation (spec §6.8/§7, Phase 7): approve / reject, bulk
 * included. Aggregates on the storefront count approved reviews only.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات';

    protected static string|UnitEnum|null $navigationGroup = 'التسويق';

    protected static ?int $navigationSort = 41;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', ReviewStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('product.name')->label('المنتج')->disabled(),
            TextInput::make('user.name')->label('العميل')->disabled(),
            TextInput::make('rating')->label('التقييم')->disabled(),
            Textarea::make('comment')->label('التعليق')->disabled()->columnSpanFull(),
            Toggle::make('is_verified_purchase')->label('عملية شراء موثقة')->disabled(),
            Select::make('status')->label('الحالة')
                ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()])->all())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')->label('المنتج')->searchable()->limit(30),
                TextColumn::make('user.name')->label('العميل')->searchable(),
                TextColumn::make('rating')->label('التقييم')->formatStateUsing(fn ($state) => str_repeat('★', (int) $state)),
                TextColumn::make('comment')->label('التعليق')->limit(50)->wrap(),
                IconColumn::make('is_verified_purchase')->label('شراء موثق')->boolean(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ReviewStatus $state) => $state->label())
                    ->color(fn (ReviewStatus $state) => $state->color()),
                TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                Action::make('approve')->label('اعتماد')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Approved)
                    ->action(fn (Review $record) => $record->update(['status' => ReviewStatus::Approved])),
                Action::make('reject')->label('رفض')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Rejected)
                    ->action(fn (Review $record) => $record->update(['status' => ReviewStatus::Rejected])),
            ])
            ->toolbarActions([
                BulkAction::make('approveSelected')->label('اعتماد المحدد')
                    ->action(fn (Collection $records) => $records->each->update(['status' => ReviewStatus::Approved])),
                BulkAction::make('rejectSelected')->label('رفض المحدد')->color('danger')
                    ->action(fn (Collection $records) => $records->each->update(['status' => ReviewStatus::Rejected])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
