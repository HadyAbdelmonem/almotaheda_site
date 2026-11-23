<?php

namespace App\Models;

use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $guarded = ['id'];

    /** Admin-only fields that must never be serialized to the storefront (spec §9). */
    protected $hidden = ['cost_price'];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sale_ends_at' => 'datetime',
            'min_order_quantity' => 'decimal:3',
            'quantity_step' => 'decimal:3',
            'max_order_quantity' => 'decimal:3',
            'stock_quantity' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'sales_count' => 'decimal:3',
            'has_variants' => 'boolean',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->catalog_identity = ArabicNormalizer::catalogIdentity(
                $product->model_code,
                $product->brand_id !== null ? (string) $product->brand_id : ($product->brand?->name ?? '')
            );
            $product->search_text = self::buildSearchText($product);
        });
    }

    public static function buildSearchText(Product $product): string
    {
        $parts = [$product->name, $product->model_code, $product->brand?->name];
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $parts[] = $variant->sku;
            }
        }

        return ArabicNormalizer::normalize(implode(' ', array_filter($parts)));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function effectiveLowStockThreshold(): string
    {
        return (string) ($this->low_stock_threshold ?? setting('low_stock_default', 5));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (['thumb' => 400, 'medium' => 800, 'large' => 1200] as $name => $width) {
            $this->addMediaConversion($name)->width($width)->format('webp')->nonQueued();
        }
    }
}
