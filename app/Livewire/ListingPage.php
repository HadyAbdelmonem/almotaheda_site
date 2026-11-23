<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\CatalogAttributeService;
use App\Services\ProductSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reusable catalog listing (spec §6.3) for category, brand and search
 * pages. All filter/sort/page state lives in the URL query string.
 * Technical filters are data-driven from category_attributes and are
 * validated server-side against a whitelist of keys and present values —
 * arbitrary query keys are ignored, never trusted.
 */
class ListingPage extends Component
{
    use WithPagination;

    /** 'category' | 'brand' | 'search' */
    public string $mode = 'category';

    public string $slug = '';

    #[Url(as: 'q')]
    public string $searchQuery = '';

    /** @var array<int> */
    #[Url(as: 'brands')]
    public array $brandIds = [];

    #[Url(as: 'min')]
    public ?string $priceMin = null;

    #[Url(as: 'max')]
    public ?string $priceMax = null;

    #[Url(as: 'stock')]
    public bool $inStock = false;

    #[Url]
    public string $sort = 'latest';

    #[Url(as: 'child')]
    public ?int $childId = null;

    /** @var array<string, string> attribute key => selected value */
    #[Url(as: 'attr')]
    public array $attr = [];

    public function mount(string $slug = '', string $mode = 'category'): void
    {
        $this->mode = $mode;
        $this->slug = $slug;
        if ($mode === 'search') {
            $this->searchQuery = (string) request()->query('q', $slug);
        }
    }

    public function updated(mixed $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['brandIds', 'priceMin', 'priceMax', 'inStock', 'attr', 'childId']);
        $this->resetPage();
    }

    public function toggleBrand(int $brandId): void
    {
        $this->brandIds = in_array($brandId, $this->brandIds, true)
            ? array_values(array_diff($this->brandIds, [$brandId]))
            : [...$this->brandIds, $brandId];
        $this->resetPage();
    }

    public function setAttr(string $key, string $value): void
    {
        if ($value === '') {
            unset($this->attr[$key]);
        } else {
            $this->attr[$key] = $value;
        }
        $this->resetPage();
    }

    /** The resolved category for category mode (404 when missing/inactive). */
    public function category(): ?Category
    {
        if ($this->mode !== 'category') {
            return null;
        }

        return Category::query()->where('slug', $this->slug)->where('is_active', true)
            ->with('children', 'parent')->firstOrFail();
    }

    public function brand(): ?Brand
    {
        if ($this->mode !== 'brand') {
            return null;
        }

        return Brand::query()->where('slug', $this->slug)->where('is_active', true)->firstOrFail();
    }

    /** Category ids in scope: the category plus its active children (max 2 levels). */
    public function categoryIds(?Category $category): array
    {
        if ($category === null) {
            return [];
        }
        if ($this->childId !== null) {
            $child = $category->children->firstWhere('id', $this->childId);

            return $child !== null && $child->is_active ? [$child->id] : [$category->id];
        }

        return [$category->id, ...$category->children->where('is_active', true)->pluck('id')->all()];
    }

    /** Base scope query before filters (active products only). */
    protected function scopeQuery(?Category $category, ?Brand $brand): Builder
    {
        $query = Product::query()->where('is_active', true);

        if ($this->mode === 'category' && $category !== null) {
            $query->whereIn('category_id', $this->categoryIds($category));
        } elseif ($this->mode === 'brand' && $brand !== null) {
            $query->where('brand_id', $brand->id);
        } elseif ($this->mode === 'search') {
            app(ProductSearchService::class)->applyToQuery($query, $this->searchQuery);
        }

        return $query;
    }

    public function render(): View
    {
        $category = $this->category();
        $brand = $this->brand();
        $scope = $this->scopeQuery($category, $brand);

        // --- Filter metadata from the result set (before attribute filters).
        $availableBrandIds = (clone $scope)->distinct()->pluck('brand_id')->filter()->all();
        $availableBrands = Brand::query()->whereIn('id', $availableBrandIds)->where('is_active', true)->orderBy('sort_order')->get();
        $priceBounds = [
            'min' => (clone $scope)->min('price'),
            'max' => (clone $scope)->max('price'),
        ];

        // Filterable technical attributes for this scope + their present values.
        $filterAttributes = $this->filterableAttributes($category, $scope);
        $attrOptions = [];
        foreach ($filterAttributes as $attribute) {
            $scopeProductIds = (clone $scope)->pluck('id');
            $values = ProductAttributeValue::query()
                ->where('category_attribute_id', $attribute->id)
                ->whereIn('product_id', $scopeProductIds)
                ->get()
                ->map(fn ($v) => $this->filterValueOf($v))
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->unique()
                ->sort()
                ->values()
                ->take((int) setting('catalog_filter_max_options', 12));
            if ($values->isNotEmpty()) {
                $attrOptions[$attribute->key] = [
                    'attribute' => $attribute,
                    'values' => $values,
                    'labels' => $values->mapWithKeys(fn ($v) => [$v => $this->filterLabelOf($attribute, $v)]),
                ];
            }
        }

        // Server-side whitelist: drop unknown keys and invalid values.
        // Select attributes validate against their defined options (a valid
        // option with no matching products yields an empty result, not an
        // unfiltered one); other types validate against present values.
        $this->attr = collect($this->attr)
            ->filter(function ($value, $key) use ($attrOptions) {
                if (! isset($attrOptions[$key])) {
                    return false;
                }
                $attribute = $attrOptions[$key]['attribute'];
                if ($attribute->data_type === 'select') {
                    return collect($attribute->options ?? [])->pluck('value')->map(fn ($v) => (string) $v)->contains((string) $value);
                }

                return $attrOptions[$key]['values']->contains((string) $value);
            })
            ->map(fn ($v) => (string) $v)
            ->all();
        $this->brandIds = array_values(array_intersect(
            array_map('intval', $this->brandIds),
            $availableBrands->pluck('id')->map(fn ($id) => (int) $id)->all()
        ));

        // --- Apply filters.
        $query = clone $scope;
        if ($this->brandIds !== [] && $this->mode !== 'brand') {
            $query->whereIn('brand_id', $this->brandIds);
        }
        if ($this->priceMin !== null && is_numeric($this->priceMin)) {
            $query->where('price', '>=', $this->priceMin);
        }
        if ($this->priceMax !== null && is_numeric($this->priceMax)) {
            $query->where('price', '<=', $this->priceMax);
        }
        if ($this->inStock) {
            $query->where(function (Builder $q) {
                $q->where('track_stock', false)
                    ->orWhere('stock_quantity', '>', 0)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('is_active', true)->where('stock_quantity', '>', 0));
            });
        }
        foreach ($this->attr as $key => $value) {
            $attribute = $attrOptions[$key]['attribute'];
            $query->whereHas('attributeValues', function (Builder $q) use ($attribute, $value) {
                $q->where('category_attribute_id', $attribute->id)
                    ->where(function (Builder $inner) use ($attribute, $value) {
                        match ($attribute->data_type) {
                            'number' => $inner->where('value_number', $value),
                            'boolean' => $inner->where('value_boolean', $value === '1'),
                            default => $inner->where('value_text', $value),
                        };
                    });
            });
        }

        // --- Sort with a stable secondary key. Search results default to
        // relevance ranking (exact model/SKU, then full-token, then partial).
        if ($this->mode === 'search' && $this->sort === 'latest') {
            $rankedIds = app(ProductSearchService::class)
                ->rankIds((clone $query)->pluck('id')->all(), $this->searchQuery);
            if ($rankedIds !== []) {
                $cases = implode(' ', array_map(fn ($i) => 'WHEN ? THEN '.$i, array_keys($rankedIds)));
                $query->orderByRaw("CASE id {$cases} ELSE 999999 END", $rankedIds);
            }
        }
        $query = match ($this->sort) {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderBy('id'),
            'best_sellers' => $query->orderByDesc('sales_count')->orderBy('id'),
            'latest' => $this->mode === 'search' ? $query : $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $products = $query
            ->with(['brand', 'category.parent', 'variants' => fn ($q) => $q->where('is_active', true), 'priceTiers', 'media'])
            ->paginate(24);

        $title = match ($this->mode) {
            'brand' => $brand?->name,
            'search' => __('store.search_results_for', ['q' => $this->searchQuery]),
            default => $category?->name,
        };

        return view('livewire.listing-page', [
            'products' => $products,
            'category' => $category,
            'brand' => $brand,
            'availableBrands' => $availableBrands,
            'priceBounds' => $priceBounds,
            'attrOptions' => $attrOptions,
            'title' => $title,
            'hasActiveFilters' => $this->brandIds !== [] || $this->priceMin !== null || $this->priceMax !== null
                || $this->inStock || $this->attr !== [] || $this->childId !== null,
        ])->layout('layouts.app', [
            'title' => $title,
            // Search pages are always noindex; filtered/sorted listing
            // URLs are noindex too, canonicalizing to the base URL (§6.3).
            'robots' => ($this->mode === 'search' || $this->hasQueryFilters()) ? 'noindex,follow' : null,
            'canonical' => $this->baseUrl(),
            'metaDescription' => match ($this->mode) {
                'brand' => $brand?->name,
                'search' => $title,
                default => $category?->meta_description ?: $category?->name,
            },
        ]);
    }

    public function hasQueryFilters(): bool
    {
        return request()->query->count() > 0;
    }

    public function baseUrl(): string
    {
        return match ($this->mode) {
            'brand' => url('brands/'.$this->slug),
            'search' => url('search'),
            default => url('categories/'.$this->slug),
        };
    }

    /** Filterable attributes applying to this scope. */
    private function filterableAttributes(?Category $category, Builder $scope): Collection
    {
        $service = app(CatalogAttributeService::class);
        if ($category !== null) {
            return $service->filterableForCategory($category);
        }
        // Brand/search scope: attributes of the categories present in scope.
        $categoryIds = (clone $scope)->distinct()->pluck('category_id')->filter()->all();
        $categories = Category::query()->whereIn('id', $categoryIds)->with('parent')->get();

        return $categories
            ->flatMap(fn (Category $c) => $service->filterableForCategory($c))
            ->unique('key')
            ->values();
    }

    private function filterValueOf(ProductAttributeValue $value): ?string
    {
        return match ($value->attribute?->data_type) {
            'number' => $value->value_number !== null ? rtrim(rtrim((string) $value->value_number, '0'), '.') : null,
            'boolean' => $value->value_boolean !== null ? ($value->value_boolean ? '1' : '0') : null,
            default => $value->value_text,
        };
    }

    private function filterLabelOf(CategoryAttribute $attribute, string $value): string
    {
        if ($attribute->data_type === 'boolean') {
            return $value === '1' ? 'نعم' : 'لا';
        }
        if ($attribute->data_type === 'select') {
            $option = collect($attribute->options ?? [])->firstWhere('value', $value);

            return $option['label'] ?? $value;
        }
        if ($attribute->data_type === 'number' && $attribute->unit_label) {
            return $value.' '.$attribute->unit_label;
        }

        return $value;
    }
}
