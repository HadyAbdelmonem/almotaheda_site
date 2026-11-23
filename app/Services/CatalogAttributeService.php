<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Support\Collection;

/**
 * Dynamic technical attributes (spec §4.12).
 * A parent category's active attributes are inherited by its children;
 * a child may add attributes but cannot redefine an inherited key.
 */
class CatalogAttributeService
{
    /** All active attributes applying to a category, including inherited ones. */
    public function forCategory(Category $category): Collection
    {
        $chain = collect();
        $current = $category;
        while ($current !== null) {
            $chain->prepend($current);
            $current = $current->parent;
        }

        $byKey = collect();
        foreach ($chain as $cat) {
            foreach ($cat->attributes()->where('is_active', true)->orderBy('sort_order')->get() as $attribute) {
                // First definition (closest to the root) wins for a key;
                // descendants must not redefine inherited keys.
                $byKey->put($attribute->key, $attribute);
            }
        }

        return $byKey->values();
    }

    /** Only active + filterable attributes (for listing filters). */
    public function filterableForCategory(Category $category): Collection
    {
        return $this->forCategory($category)->where('is_filterable', true)->values();
    }

    /** Keys that are already defined by an ancestor of the given category. */
    public function inheritedKeys(Category $category): array
    {
        if ($category->parent === null) {
            return [];
        }

        return $this->forCategory($category->parent)->pluck('key')->all();
    }

    /** Validate that a value matches the attribute type; returns an error key or null. */
    public function validateValue(CategoryAttribute $attribute, mixed $value): ?string
    {
        return match ($attribute->data_type) {
            'number' => is_numeric($value) ? null : 'not_numeric',
            'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true) ? null : 'not_boolean',
            'select' => $this->validateSelect($attribute, $value),
            default => is_string($value) && $value !== '' ? null : 'empty_text',
        };
    }

    private function validateSelect(CategoryAttribute $attribute, mixed $value): ?string
    {
        $options = collect($attribute->options ?? [])->pluck('value')->map(fn ($v) => (string) $v);

        return $options->contains((string) $value) ? null : 'invalid_option';
    }
}
