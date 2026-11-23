<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم الماركة')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set, $record) => $record === null ? $set('slug', Str::slug((string) $state) ?: 'brand-'.substr(hash('sha256', (string) $state), 0, 8)) : null),
                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('حروف إنجليزية وشرطات فقط.'),
                SpatieMediaLibraryFileUpload::make('logo')
                    ->label('شعار الماركة')
                    ->collection('logo')
                    ->image()
                    ->maxSize(4096),
                Toggle::make('is_authorized_dealer')
                    ->label('وكيل معتمد'),
                Toggle::make('is_active')
                    ->label('مفعّلة')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('ترتيب العرض')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
