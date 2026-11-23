<?php

namespace App\Models;

use App\Services\CatalogAttributeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class CategoryAttribute extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // A child category may add attributes but cannot redefine an
        // inherited key (spec SS4.12).
        static::saving(function (CategoryAttribute $attribute) {
            $category = $attribute->category;
            if ($category === null || $category->parent_id === null) {
                return;
            }
            $inherited = app(CatalogAttributeService::class)->inheritedKeys($category);
            if (in_array($attribute->key, $inherited, true)) {
                throw ValidationException::withMessages([
                    'key' => 'هذا المفتاح موروث من التصنيف الأب ولا يمكن إعادة تعريفه.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
