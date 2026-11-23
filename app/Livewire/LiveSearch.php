<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\ProductSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Header live search (spec §6.4): debounced (300ms in the view), top 6
 * results with thumbnail, name, model code and unit price. Ranking is
 * delegated to ProductSearchService: exact model/SKU first, then
 * full-token matches, then partial matches. Keyboard navigation
 * (arrows/Enter/Escape) is handled in the Blade view with Alpine.
 */
class LiveSearch extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    public function render(): View
    {
        $results = collect();
        $query = trim($this->query);
        if (mb_strlen($query) >= 2) {
            $service = app(ProductSearchService::class);
            $matchedIds = $service->applyToQuery(
                Product::query()->where('is_active', true),
                $query
            )->pluck('id')->all();

            $rankedIds = array_slice($service->rankIds($matchedIds, $query), 0, 6);
            if ($rankedIds !== []) {
                $results = Product::query()
                    ->whereIn('id', $rankedIds)
                    ->with(['brand', 'category.parent', 'variants' => fn ($q) => $q->where('is_active', true), 'priceTiers', 'media'])
                    ->get()
                    ->sortBy(fn (Product $p) => array_search($p->id, $rankedIds, true))
                    ->values();
            }
        }

        return view('livewire.live-search', [
            'results' => $results,
            'query' => $query,
        ]);
    }
}
