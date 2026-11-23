<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Looks at a cart as it is NOW: the customer may have left it for days
 * while products were removed, colours dropped or stock sold.
 *
 * Every line gets null (fine) or a CartIssue. The cart drawer and checkout
 * show the issues and keep "Checkout" / "Continue" disabled while any line
 * has one. This is for display; PlaceOrder checks the same rules again
 * under row locks, so nothing slips through between the page and the click.
 */
class CartReview
{
    /**
     * @return Collection<int, array{item: CartItem, variant: ?ProductVariant, issue: ?CartIssue, available: ?int}>
     */
    public function lines(Cart $cart): Collection
    {
        $items = $cart->items()->orderBy('id')->get();

        // Include deleted rows on purpose: we want to say "removed", not lose the line silently.
        $variants = ProductVariant::withTrashed()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('media'), 'attributeValues.productAttribute'])
            ->whereKey($items->pluck('product_variant_id')->all())
            ->get()
            ->keyBy('id');

        return $items->map(function (CartItem $item) use ($variants): array {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($item->product_variant_id);
            [$issue, $available] = $this->issue($variant, $item->quantity);

            return ['item' => $item, 'variant' => $variant, 'issue' => $issue, 'available' => $available];
        });
    }

    public function hasIssues(Cart $cart): bool
    {
        return $this->lines($cart)->contains(fn (array $line): bool => $line['issue'] !== null);
    }

    /** @return array{0: ?CartIssue, 1: ?int} */
    private function issue(?ProductVariant $variant, int $quantity): array
    {
        $product = $variant?->product;

        if ($product === null || ! $product->isPublished()) {
            return [CartIssue::Removed, null];
        }

        if ($variant->trashed() || ! $variant->is_active) {
            return [CartIssue::VariantGone, null];
        }

        if ($variant->track_inventory && $variant->stock_quantity === 0) {
            return [CartIssue::SoldOut, 0];
        }

        if ($variant->track_inventory && $variant->stock_quantity < $quantity) {
            return [CartIssue::OnlyLeft, $variant->stock_quantity];
        }

        return [null, $variant->track_inventory ? $variant->stock_quantity : null];
    }

    /** Message for one line, in the current locale. */
    public static function message(CartIssue $issue, ?int $available = null, ?int $inCart = null): string
    {
        return __("cart.issues.{$issue->value}", ['count' => $available ?? 0, 'in_cart' => $inCart ?? 0]);
    }
}
