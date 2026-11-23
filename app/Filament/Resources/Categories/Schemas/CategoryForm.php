<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم التصنيف')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set, $record) => $record === null ? $set('slug', Str::slug((string) $state) ?: 'category-'.substr(hash('sha256', (string) $state), 0, 8)) : null),
                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('parent_id')
                    ->label('التصنيف الأب')
                    ->options(fn (?Model $record) => Category::query()
                        ->whereNull('parent_id')
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->getKey()))
                        ->orderBy('sort_order')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->helperText('اتركه فارغاً للتصنيف الرئيسي. التصنيفات مستويان كحد أقصى.'),
                Select::make('icon')
                    ->label('الأيقونة')
                    ->options(config('icons'))
                    ->searchable()
                    ->nullable()
                    ->helperText('التصنيف الفرعي يرث أيقونة الأب عند تركه فارغاً.'),
                SpatieMediaLibraryFileUpload::make('image')
                    ->label('صورة التصنيف')
                    ->collection('image')
                    ->image()
                    ->maxSize(4096),
                TextInput::make('sort_order')
                    ->label('ترتيب العرض')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true),
                TextInput::make('meta_title')
                    ->label('عنوان SEO')
                    ->maxLength(255),
                Textarea::make('meta_description')
                    ->label('وصف SEO')
                    ->rows(2),
            ]);
    }
}
