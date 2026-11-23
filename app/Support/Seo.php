<?php

namespace App\Support;

use App\Enums\ReviewStatus;
use App\Models\Product;

/**
 * Structured-data builders (spec §9). JSON-LD is built ONLY from
 * storefront-safe fields — never cost_price, admin notes or any
 * internal/payment data.
 */
final class Seo
{
    /** @return array<string, mixed> */
    public static function productJsonLd(Product $product): array
    {
        $card = ProductDisplay::cardPricing($product);
        $price = $card['pricing']['unit_price'];
        $state = ProductDisplay::stockState($product);

        $approved = $product->reviews()->where('status', ReviewStatus::Approved);
        $reviewCount = (clone $approved)->count();
        $average = $reviewCount > 0 ? round((float) (clone $approved)->avg('rating'), 1) : null;

        $images = $product->getMedia('gallery')->map(fn ($media) => $media->getUrl('large'))->values()->all();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->model_code,
            'url' => route('products.show', $product->slug),
            'description' => $product->meta_description ?: ($product->short_description ?: $product->name),
            'offers' => [
                '@type' => 'Offer',
                'url' => route('products.show', $product->slug),
                'priceCurrency' => 'EGP',
                'price' => Money::of($price),
                'availability' => $state === 'out'
                    ? 'https://schema.org/OutOfStock'
                    : 'https://schema.org/InStock',
            ],
        ];

        if ($images !== []) {
            $data['image'] = $images;
        }
        if ($product->brand !== null) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }
        if ($reviewCount > 0 && $average !== null) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $average,
                'reviewCount' => $reviewCount,
            ];
        }

        return $data;
    }

    /**
     * @param  array<int, array{label: string, url?: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbJsonLd(array $items): array
    {
        $elements = [];
        foreach (array_values($items) as $i => $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['label'],
            ];
            if (! empty($item['url'])) {
                $element['item'] = $item['url'];
            }
            $elements[] = $element;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /** @return array<string, mixed> */
    public static function organizationJsonLd(): array
    {
        $phones = (array) setting('phones', []);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => trim((string) setting('store_name', '')) ?: __('store.name'),
            'url' => url('/'),
        ];
        $address = trim((string) setting('address', ''));
        if ($address !== '') {
            $data['address'] = $address;
        }
        $email = trim((string) setting('email', ''));
        if ($email !== '') {
            $data['email'] = $email;
        }
        if ($phones !== []) {
            $data['telephone'] = (string) $phones[0];
        }
        $social = (array) setting('social_links', []);
        $sameAs = array_values(array_filter(array_map(fn ($v) => is_string($v) ? $v : null, $social)));
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /** Encode JSON-LD safely for embedding in a <script> tag. */
    public static function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
