<?php

namespace Tests\Feature\Checkout;

use App\Enums\ProductStatus;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Services\Cart\CartIssue;
use App\Services\Cart\CartReview;
use App\Services\Checkout\PlaceOrder;
use App\Services\Inventory;

/**
 * A cart left alone for a while: the store changed meanwhile. The review
 * must name every problem, and placing the order must refuse until fixed.
 */
class CartReviewTest extends CheckoutTestCase
{
    public function test_every_kind_of_change_is_reported_per_line(): void
    {
        $fine = $this->variant(stock: 5);
        $productGone = $this->variant(stock: 5);
        $unpublished = $this->variant(stock: 5);
        $colourDropped = $this->variant(stock: 5);
        $colourOff = $this->variant(stock: 5);
        $soldOut = $this->variant(stock: 2);
        $fewLeft = $this->variant(stock: 3);
        $made = $this->variant(stock: 0);
        $made->update(['track_inventory' => false]);

        $cart = $this->cart([
            $fine->id => 2, $productGone->id => 1, $unpublished->id => 1, $colourDropped->id => 1,
            $colourOff->id => 1, $soldOut->id => 1, $fewLeft->id => 5, $made->id => 9,
        ]);

        // Time passes…
        $productGone->product->delete();
        $unpublished->product->update(['status' => ProductStatus::Archived]);
        $colourDropped->delete();
        $colourOff->update(['is_active' => false]);
        app(Inventory::class)->setCount($soldOut, 0, note: 'sold in shop');
        app(Inventory::class)->setCount($fewLeft, 1, note: 'sold in shop');

        $lines = app(CartReview::class)->lines($cart)->keyBy(fn ($l) => $l['item']->product_variant_id);

        $this->assertNull($lines[$fine->id]['issue']);
        $this->assertNull($lines[$made->id]['issue'], 'untracked stock never runs out');
        $this->assertSame(CartIssue::Removed, $lines[$productGone->id]['issue']);
        $this->assertSame(CartIssue::Removed, $lines[$unpublished->id]['issue']);
        $this->assertSame(CartIssue::VariantGone, $lines[$colourDropped->id]['issue']);
        $this->assertSame(CartIssue::VariantGone, $lines[$colourOff->id]['issue']);
        $this->assertSame(CartIssue::SoldOut, $lines[$soldOut->id]['issue']);
        $this->assertSame(CartIssue::OnlyLeft, $lines[$fewLeft->id]['issue']);
        $this->assertSame(1, $lines[$fewLeft->id]['available']);
        $this->assertFalse(CartIssue::OnlyLeft->mustRemove());
        $this->assertTrue(CartIssue::VariantGone->mustRemove());

        $this->assertSame(
            'Only 1 left, you have 5 — lower the quantity',
            CartReview::message(CartIssue::OnlyLeft, 1, 5),
        );
        $this->assertTrue(app(CartReview::class)->hasIssues($cart));
    }

    public function test_the_order_is_refused_until_the_cart_is_fixed(): void
    {
        $ok = $this->variant(stock: 5);
        $dropped = $this->variant(stock: 5);
        $cart = $this->cart([$ok->id => 1, $dropped->id => 1]);
        $dropped->delete();

        try {
            app(PlaceOrder::class)->handle($this->customer, $this->data());
            $this->fail('A cart with a removed variant must not become an order');
        } catch (CheckoutException) {
            // expected
        }
        $this->assertSame(0, Order::count());

        // The customer removes the line, then it works.
        $cart->items()->where('product_variant_id', $dropped->id)->delete();
        $this->assertFalse(app(CartReview::class)->hasIssues($cart));
        $this->assertSame(1, app(PlaceOrder::class)->handle($this->customer, $this->data())->items()->count());
    }

    public function test_a_clean_cart_has_no_issues(): void
    {
        $cart = $this->cart([$this->variant(stock: 5)->id => 2]);

        $this->assertFalse(app(CartReview::class)->hasIssues($cart));
    }
}
