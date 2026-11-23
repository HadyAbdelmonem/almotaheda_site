<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Brand extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Brand names are part of each product's search_text (spec SS8.4):
        // a rename must rebuild the search text of all its products.
        static::saved(function (Brand $brand) {
            if ($brand->wasChanged('name')) {
                $brand->products()->get()->each(function (Product $product) {
                    $product->load('variants');
                    $product->save();
                });
            }
        });
    }

    protected function casts(): array
    {
        return ['is_authorized_dealer' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }
}
