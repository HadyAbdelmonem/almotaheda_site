<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Variant SKUs are part of the product's search_text (spec SS8.4):
        // keep it fresh whenever a variant is saved or removed.
        static::saved(function (ProductVariant $variant) {
            $product = $variant->product;
            if ($product !== null && ! $product->trashed()) {
                $product->load('variants');
                $product->save();
            }
        });
        static::deleted(function (ProductVariant $variant) {
            $product = $variant->product;
            if ($product !== null && ! $product->trashed()) {
                $product->load('variants');
                $product->save();
            }
        });
    }

    protected $hidden = ['cost_price'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sale_ends_at' => 'datetime',
            'stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }
}
