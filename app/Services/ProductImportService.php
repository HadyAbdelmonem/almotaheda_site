<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Support\ArabicNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Excel catalog import/export (spec 7.1).
 *
 * Upsert key is the deterministic catalog_identity (normalized
 * model_code + brand id, or an empty brand key when brand is null),
 * exactly as computed by the Product model on save. Rows are processed
 * in chunks of at most 200 per call so one HTTP request never processes
 * a whole large file; the caller (the admin Livewire page) loops.
 */
class ProductImportService
{
    public const MAX_ROWS_PER_REQUEST = 200;

    public const MAX_ROWS_PER_FILE = 5000;

    /** Exact template/export header (spec 7.1), in order. */
    public const HEADERS = [
        'name', 'model_code', 'brand', 'category', 'subcategory',
        'cost_price', 'price', 'sale_price', 'stock_quantity', 'unit_code',
        'min_order_quantity', 'quantity_step', 'max_order_quantity',
        'specs', 'attributes', 'short_description',
    ];

    public function __construct(
        private readonly QuantityService $quantities,
        private readonly CatalogAttributeService $attributeService,
        private readonly StockService $stock,
    ) {}

    /**
     * Process a slice of data rows (each row is a list of cells in
     * HEADERS order). Returns per-chunk tallies and failed rows.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{created: int, updated: int, failed: array<int, array{row: int, model_code: string, reason: string}>}
     */
    public function processChunk(array $rows, int $offset, int $limit = self::MAX_ROWS_PER_REQUEST): array
    {
        $limit = max(1, min($limit, self::MAX_ROWS_PER_REQUEST));
        $slice = array_slice($rows, $offset, $limit);
        $result = ['created' => 0, 'updated' => 0, 'failed' => []];

        foreach ($slice as $i => $cells) {
            $rowNumber = $offset + $i + 2; // +1 header, +1 for 1-based rows
            $data = $this->mapRow(is_array($cells) ? array_values($cells) : []);
            try {
                $outcome = $this->importRow($data);
                $result[$outcome]++;
            } catch (\Throwable $e) {
                $result['failed'][] = [
                    'row' => $rowNumber,
                    'model_code' => (string) ($data['model_code'] ?? ''),
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /** @param array<int, mixed> $cells */
    private function mapRow(array $cells): array
    {
        $data = [];
        foreach (self::HEADERS as $index => $key) {
            $value = $cells[$index] ?? null;
            $data[$key] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }

    /**
     * Import one row. Returns 'created' or 'updated'.
     *
     * @throws InvalidArgumentException with an Arabic, admin-actionable reason.
     */
    public function importRow(array $data): string
    {
        $name = (string) ($data['name'] ?? '');
        $modelCode = (string) ($data['model_code'] ?? '');
        if ($name === '' || $modelCode === '') {
            throw new InvalidArgumentException('اسم المنتج وكود الموديل مطلوبان.');
        }

        $unitCode = (string) ($data['unit_code'] ?? 'piece');
        if (! array_key_exists($unitCode, config('units', []))) {
            throw new InvalidArgumentException("وحدة بيع غير صالحة: {$unitCode}");
        }

        $price = $data['price'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price)) {
            throw new InvalidArgumentException('سعر البيع مطلوب ويجب أن يكون رقماً.');
        }

        $category = $this->resolveCategory((string) ($data['category'] ?? ''), (string) ($data['subcategory'] ?? ''));

        $brand = $this->resolveBrand((string) ($data['brand'] ?? ''));

        $minQty = $this->decimalOrDefault($data['min_order_quantity'] ?? null, (string) config("units.{$unitCode}.default_min", '1'));
        $step = $this->decimalOrDefault($data['quantity_step'] ?? null, (string) config("units.{$unitCode}.default_step", '1'));
        $maxQty = ($data['max_order_quantity'] ?? null) === null || $data['max_order_quantity'] === ''
            ? null : $this->quantities->normalize((string) $data['max_order_quantity']);

        // Validate quantity rules server-side (spec 4.11): a piece product
        // rejects a fractional step/min, a meter product may use fractions.
        try {
            $this->quantities->validate($minQty, $minQty, $step, $maxQty);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('قواعد الكمية غير صالحة: '.$e->getMessage());
        }

        $stockQty = $this->decimalOrDefault($data['stock_quantity'] ?? null, '0');
        try {
            $this->quantities->validate($stockQty, '0', $step);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('كمية المخزون غير صالحة: '.$e->getMessage());
        }

        $attributes = $this->parseAttributes((string) ($data['attributes'] ?? ''), $category);

        return DB::transaction(function () use ($data, $name, $modelCode, $unitCode, $price, $category, $brand, $minQty, $step, $maxQty, $stockQty, $attributes) {
            $identity = ArabicNormalizer::catalogIdentity($modelCode, $brand !== null ? (string) $brand->id : '');
            $product = Product::withTrashed()->where('catalog_identity', $identity)->first();
            $created = $product === null;
            $product ??= new Product;

            $oldStock = $created ? '0' : $this->quantities->normalize((string) $product->stock_quantity);

            $product->fill([
                'category_id' => $category->id,
                'brand_id' => $brand?->id,
                'name' => $name,
                'model_code' => $modelCode,
                'short_description' => ($data['short_description'] ?? null) ?: null,
                'cost_price' => $this->decimalOrDefault($data['cost_price'] ?? null, '0'),
                'price' => $price,
                'sale_price' => ($data['sale_price'] ?? null) === null || $data['sale_price'] === '' ? null : $data['sale_price'],
                'stock_quantity' => $stockQty,
                'unit_code' => $unitCode,
                'min_order_quantity' => $minQty,
                'quantity_step' => $step,
                'max_order_quantity' => $maxQty,
                'specs' => $this->parseSpecs((string) ($data['specs'] ?? '')),
            ]);
            if ($created || ! $product->slug) {
                $product->slug = $this->uniqueSlug($modelCode, $product->id);
            }
            if ($product->trashed()) {
                $product->restore();
            }
            $product->save();

            // Structured technical attribute values (spec 4.12).
            foreach ($attributes as [$attribute, $value]) {
                $column = match ($attribute->data_type) {
                    'number' => 'value_number',
                    'boolean' => 'value_boolean',
                    default => 'value_text',
                };
                $payload = [
                    'value_text' => null, 'value_number' => null,
                    'value_boolean' => null, 'value_label_snapshot' => null,
                ];
                $payload[$column] = $attribute->data_type === 'boolean'
                    ? in_array($value, [true, 1, '1', 'true'], true)
                    : $value;
                if ($attribute->data_type === 'select') {
                    $option = collect($attribute->options ?? [])->firstWhere('value', (string) $value);
                    $payload['value_label_snapshot'] = $option['label'] ?? (string) $value;
                }
                $product->attributeValues()->updateOrCreate(
                    ['category_attribute_id' => $attribute->id],
                    $payload
                );
            }

            // Stock differences from import are audited (spec 7.1).
            if ($this->quantities->compare($oldStock, $stockQty) !== 0) {
                $diff = $this->quantities->compare($stockQty, $oldStock) > 0
                    ? $this->quantities->sub($stockQty, $oldStock)
                    : '-'.$this->quantities->sub($oldStock, $stockQty);
                $this->stock->recordMovement($product, null, $diff, StockMovementReason::Import, null, 'Excel import');
            }

            return $created ? 'created' : 'updated';
        });
    }

    private function resolveCategory(string $categoryName, string $subcategoryName): Category
    {
        if ($categoryName === '') {
            throw new InvalidArgumentException('التصنيف مطلوب.');
        }
        $category = Category::query()->whereNull('parent_id')->where('name', $categoryName)->first()
            ?? Category::query()->where('name', $categoryName)->first();
        if ($category === null) {
            throw new InvalidArgumentException("التصنيف غير موجود: {$categoryName}");
        }
        if ($subcategoryName !== '') {
            $child = Category::query()->where('parent_id', $category->id)->where('name', $subcategoryName)->first();
            if ($child === null) {
                throw new InvalidArgumentException("التصنيف الفرعي غير موجود: {$subcategoryName}");
            }

            return $child;
        }

        return $category;
    }

    private function resolveBrand(string $brandName): ?Brand
    {
        if ($brandName === '') {
            return null;
        }
        $brand = Brand::query()->where('name', $brandName)->first();
        if ($brand !== null) {
            return $brand;
        }
        // Missing brands are auto-created INACTIVE (spec 7.1); an existing
        // brand identity is never overwritten silently.
        $slug = Str::slug($brandName) ?: 'brand-'.substr(hash('sha256', $brandName), 0, 8);
        if (Brand::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.substr(hash('sha256', $brandName.microtime()), 0, 6);
        }

        return Brand::create(['name' => $brandName, 'slug' => $slug, 'is_active' => false]);
    }

    /** @return array<int, array{label: string, value: string}> */
    private function parseSpecs(string $specs): array
    {
        if ($specs === '') {
            return [];
        }
        $pairs = [];
        foreach (explode('|', $specs) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $pos = strpos($part, ':');
            if ($pos === false) {
                $pairs[] = ['label' => $part, 'value' => ''];
            } else {
                $pairs[] = ['label' => trim(substr($part, 0, $pos)), 'value' => trim(substr($part, $pos + 1))];
            }
        }

        return $pairs;
    }

    /**
     * Parse and validate "key=value | key2=value2" against the active
     * attributes of the category tree (spec 7.1/4.12).
     *
     * @return array<int, array{0: CategoryAttribute, 1: mixed}> keyed list of [attribute, value]
     */
    private function parseAttributes(string $attributes, Category $category): array
    {
        if ($attributes === '') {
            return [];
        }
        $defined = $this->attributeService->forCategory($category)->keyBy('key');
        $parsed = [];
        foreach (explode('|', $attributes) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $pos = strpos($part, '=');
            if ($pos === false) {
                throw new InvalidArgumentException("صيغة خاصية غير صالحة: {$part}");
            }
            $key = trim(substr($part, 0, $pos));
            $value = trim(substr($part, $pos + 1));
            $attribute = $defined->get($key);
            if ($attribute === null) {
                throw new InvalidArgumentException("خاصية غير معرفة لهذا التصنيف: {$key}");
            }
            $error = $this->attributeService->validateValue($attribute, $value);
            if ($error !== null) {
                throw new InvalidArgumentException("قيمة غير صالحة للخاصية {$key}: {$value}");
            }
            $parsed[] = [$attribute, $value];
        }

        return $parsed;
    }

    private function decimalOrDefault(mixed $value, string $default): string
    {
        if ($value === null || $value === '') {
            return $this->quantities->normalize($default);
        }
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("قيمة رقمية غير صالحة: {$value}");
        }

        return $this->quantities->normalize((string) $value);
    }

    private function uniqueSlug(string $modelCode, ?int $ignoreId): string
    {
        $base = Str::slug($modelCode) ?: 'product-'.substr(hash('sha256', $modelCode), 0, 8);
        $slug = $base;
        $i = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * All catalog rows for export, in HEADERS order (spec 7.1).
     *
     * @return array<int, array<int, mixed>>
     */
    public function exportRows(): array
    {
        $rows = [self::HEADERS];
        Product::query()->with(['brand', 'category.parent', 'attributeValues.attribute'])->orderBy('id')->each(function (Product $product) use (&$rows) {
            $category = $product->category;
            $parent = $category?->parent;
            $specs = collect($product->specs ?? [])->map(fn ($pair) => ($pair['label'] ?? '').': '.($pair['value'] ?? ''))->implode(' | ');
            $attributes = $product->attributeValues->map(function ($value) {
                $raw = match ($value->attribute?->data_type) {
                    'number' => rtrim(rtrim((string) $value->value_number, '0'), '.'),
                    'boolean' => $value->value_boolean ? '1' : '0',
                    default => (string) $value->value_text,
                };

                return ($value->attribute?->key ?? '').'='.$raw;
            })->implode(' | ');
            $rows[] = [
                $product->name, $product->model_code, $product->brand?->name,
                $parent?->name ?? $category?->name, $parent ? $category?->name : null,
                (string) $product->cost_price, (string) $product->price,
                $product->sale_price !== null ? (string) $product->sale_price : null,
                rtrim(rtrim((string) $product->stock_quantity, '0'), '.'),
                $product->unit_code,
                rtrim(rtrim((string) $product->min_order_quantity, '0'), '.'),
                rtrim(rtrim((string) $product->quantity_step, '0'), '.'),
                $product->max_order_quantity !== null ? rtrim(rtrim((string) $product->max_order_quantity, '0'), '.') : null,
                $specs, $attributes, $product->short_description,
            ];
        });

        return $rows;
    }
}
