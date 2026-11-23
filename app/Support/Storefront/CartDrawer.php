<?php

namespace App\Support\Storefront;

use App\Models\Cart;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\Cart\CartIssue;
use App\Services\Cart\CartReview;
use App\Services\Cart\CartService;

/**
 * The cart drawer's contents: current prices, and for every line that
 * changed since it was added (removed, colour dropped, sold out, less
 * stock) the reason and what to do. Checkout stays disabled while any
 * line has an issue (agreed with the user).
 */
class CartDrawer
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartReview $review,
        private readonly Catalog $catalog,
    ) {}

    public function data(): array
    {
        $cart = $this->carts->current();

        if (! $cart instanceof Cart) {
            return ['lines' => [], 'count' => 0, 'subtotal' => 0, 'has_issues' => false, 'upsell' => null];
        }

        $lines = $this->review->lines($cart)->map(function (array $line): array {
            /** @var ProductVariant|null $variant */
            $variant = $line['variant'];
            $product = $variant?->product;
            /** @var CartIssue|null $issue */
            $issue = $line['issue'];
            $quantity = $line['item']->quantity;
            $counts = $issue === null || $issue === CartIssue::OnlyLeft;

            return [
                'id' => $line['item']->getKey(),
                'name' => $product?->name ?? '—',
                'url' => $product && $product->isPublished() ? route('shop.product', ['slug' => $product->slug]) : null,
                'options' => $variant?->attributeValues
                    ->sortBy(fn (ProductAttributeValue $v): int => $v->productAttribute->sort_order)
                    ->map(fn (ProductAttributeValue $v): string => $v->value)
                    ->implode(' / ') ?? '',
                'quantity' => $quantity,
                'unit_price' => (int) ($variant?->price ?? 0),
                'line_total' => $counts ? (int) ($variant?->price ?? 0) * $quantity : 0,
                'image' => $product?->getFirstMediaUrl('images', 'thumb') ?: null,
                'swatch' => $variant?->attributeValues->first(fn (ProductAttributeValue $v): bool => $v->color_hex !== null)?->color_hex,
                'max' => $variant ? CartService::maxAllowed($variant) : 0,
                'issue' => $issue ? CartReview::message($issue, $line['available'], $quantity) : null,
                'must_remove' => $issue?->mustRemove() ?? false,
            ];
        })->values();

        return [
            'lines' => $lines->all(),
            'count' => (int) $lines->sum('quantity'),
            'subtotal' => (int) $lines->sum('line_total'),
            'has_issues' => $lines->contains(fn (array $l): bool => $l['issue'] !== null),
            'upsell' => $this->upsell($cart),
        ];
    }

    /** "Complete with": a hand-picked suggestion of a product in the cart, else nothing. */
    private function upsell(Cart $cart): ?array
    {
        $productIds = $cart->items()
            ->join('product_variants', 'product_variants.id', '=', 'cart_items.product_variant_id')
            ->pluck('product_variants.product_id');

        $suggestion = $this->catalog->cardQuery()
            ->whereNotIn('products.id', $productIds)
            ->whereHas('relatedTo', fn ($q) => $q->whereIn('product_related.product_id', $productIds))
            ->first();

        return $suggestion ? $this->catalog->card($suggestion) : null;
    }
}
