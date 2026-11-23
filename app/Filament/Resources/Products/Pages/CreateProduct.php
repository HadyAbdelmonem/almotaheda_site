<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug((string) ($data['model_code'] ?? '')) ?: 'product-'.substr(hash('sha256', (string) ($data['model_code'] ?? uniqid())), 0, 8);
        }

        return $data;
    }
}
