<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Max 2 levels (spec SS5): a child cannot become a parent, a
        // category cannot be its own parent, and a category that has
        // children cannot be moved under another category.
        static::saving(function (Category $category) {
            if ($category->parent_id === null) {
                return;
            }
            if ($category->exists && (int) $category->parent_id === (int) $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'لا يمكن أن يكون التصنيف أباً لنفسه.',
                ]);
            }
            $parent = static::query()->find($category->parent_id);
            if ($parent !== null && $parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'التصنيفات مستويان كحد أقصى: التصنيف الأب يجب أن يكون رئيسياً.',
                ]);
            }
            if ($category->exists && $category->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'هذا التصنيف له تصنيفات فرعية فلا يمكن نقله تحت تصنيف آخر.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    /** Icon key, inheriting the parent's icon when null (spec §5). */
    public function effectiveIcon(): ?string
    {
        return $this->icon ?? $this->parent?->icon;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }
}
