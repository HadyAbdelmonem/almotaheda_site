<?php

namespace App\Services;

use App\Models\Product;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Arabic / model-code search (spec §6.4, §8.4).
 * The normalized query is split into tokens; EVERY token must match the
 * product's normalized search_text (AND behavior). Ranking: exact
 * model-code/SKU matches first, then full-token matches, then partial
 * matches. No external search engine in v1.
 */
class ProductSearchService
{
    /** @return array<int, string> normalized non-empty tokens */
    public function tokens(string $query): array
    {
        $normalized = ArabicNormalizer::normalize($query);

        return array_values(array_filter(explode(' ', $normalized), fn ($t) => $t !== ''));
    }

    /** Constrain a product query so every token matches search_text. */
    public function applyToQuery(Builder $query, string $search): Builder
    {
        foreach ($this->tokens($search) as $token) {
            $query->where('search_text', 'like', '%'.$this->escapeLike($token).'%');
        }

        return $query;
    }

    /**
     * Rank an already token-matched product id set: exact model code or
     * SKU first, then products whose search_text contains every token as
     * a whole word, then the rest. Returns ids in ranked order.
     *
     * @param  array<int>  $productIds
     * @return array<int>
     */
    public function rankIds(array $productIds, string $search): array
    {
        if ($productIds === []) {
            return [];
        }
        $tokens = $this->tokens($search);
        if ($tokens === []) {
            return $productIds;
        }
        $fullQuery = implode(' ', $tokens);

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->keyBy('id');

        $scored = [];
        foreach ($productIds as $id) {
            $product = $products->get($id);
            if ($product === null) {
                continue;
            }
            $score = 2; // partial token match (already guaranteed by LIKE)
            $model = ArabicNormalizer::normalize((string) $product->model_code);
            $skus = $product->variants->map(fn ($v) => ArabicNormalizer::normalize((string) $v->sku))->all();
            if ($model === $fullQuery || in_array($fullQuery, $skus, true)) {
                $score = 0; // exact model code / SKU
            } else {
                $words = explode(' ', (string) $product->search_text);
                $allWholeWords = collect($tokens)->every(fn ($t) => in_array($t, $words, true));
                if ($allWholeWords) {
                    $score = 1; // full-token match
                }
            }
            $scored[] = ['id' => $id, 'score' => $score, 'sales' => (float) $product->sales_count];
        }

        usort($scored, fn ($a, $b) => $a['score'] <=> $b['score'] ?: $b['sales'] <=> $a['sales'] ?: $a['id'] <=> $b['id']);

        return array_map(fn ($row) => $row['id'], $scored);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
