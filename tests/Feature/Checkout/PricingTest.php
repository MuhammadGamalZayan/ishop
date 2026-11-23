<?php

namespace Tests\Feature\Checkout;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\TaxRate;
use App\Services\Checkout\Pricing;

class PricingTest extends CheckoutTestCase
{
    private function pricing(): Pricing
    {
        return app(Pricing::class);
    }

    public function test_spread_always_adds_up_exactly(): void
    {
        $this->assertSame([34, 33, 33], $this->pricing()->spread(100, [1, 1, 1]));
        $this->assertSame([0, 0, 0], $this->pricing()->spread(0, [5, 5, 5]));
        $this->assertSame(1001, array_sum($this->pricing()->spread(1001, [333, 777, 1])));
        $this->assertSame([750, 250], $this->pricing()->spread(1000, [300, 100]));
    }

    public function test_totals_with_shipping_and_inclusive_vat_on_goods_and_shipping(): void
    {
        $a = $this->variant(price: 114000); // 1,140.00 EGP
        $b = $this->variant(price: 57000);

        $quote = $this->pricing()->quote([
            ['variant' => $a, 'quantity' => 2],
            ['variant' => $b, 'quantity' => 1],
        ], area: $this->area);

        $this->assertSame(285000, $quote->subtotal);
        $this->assertSame(6000, $quote->shippingTotal);
        $this->assertSame(291000, $quote->grandTotal);
        // VAT inside 2,850 = 350 ; inside 60 = 7.37 → 736.84 ≈ 737 piastres
        $vat = TaxRate::query()->first();
        $this->assertSame($vat->taxIncludedIn(228000) + $vat->taxIncludedIn(57000) + $vat->taxIncludedIn(6000), $quote->taxTotal);
        $this->assertSame(35737, $quote->taxTotal);
    }

    public function test_percentage_coupon_is_capped_and_spread_over_lines(): void
    {
        $a = $this->variant(price: 300000);
        $b = $this->variant(price: 100000);
        $coupon = Coupon::create(['code' => 'TWENTY', 'type' => CouponType::Percentage, 'value' => 2000, 'max_discount' => 50000]);

        $quote = $this->pricing()->quote([['variant' => $a, 'quantity' => 1], ['variant' => $b, 'quantity' => 1]], $coupon, $this->area, $this->customer);

        $this->assertSame(50000, $quote->discountTotal, '20% of 4,000 = 800, capped at 500');
        $this->assertSame([37500, 12500], array_map(fn ($l) => $l->discount, $quote->lines));
        $this->assertSame(400000 - 50000 + 6000, $quote->grandTotal);
    }

    public function test_fixed_coupon_never_exceeds_the_goods(): void
    {
        $a = $this->variant(price: 20000);
        $coupon = Coupon::create(['code' => 'BIG', 'type' => CouponType::Fixed, 'value' => 99999999]);

        $quote = $this->pricing()->quote([['variant' => $a, 'quantity' => 1]], $coupon, $this->area);

        $this->assertSame(20000, $quote->discountTotal);
        $this->assertSame(6000, $quote->grandTotal, 'only shipping left');
    }

    public function test_free_shipping_coupon(): void
    {
        $a = $this->variant(price: 20000);
        $coupon = Coupon::create(['code' => 'SHIP', 'type' => CouponType::FreeShipping, 'value' => 0]);

        $quote = $this->pricing()->quote([['variant' => $a, 'quantity' => 1]], $coupon, $this->area);

        $this->assertSame(0, $quote->shippingTotal);
        $this->assertSame(0, $quote->discountTotal);
        $this->assertSame(20000, $quote->grandTotal);
    }

    public function test_an_unusable_coupon_is_reported_and_ignored(): void
    {
        $a = $this->variant(price: 20000);
        $coupon = Coupon::create(['code' => 'MIN', 'type' => CouponType::Fixed, 'value' => 1000, 'min_subtotal' => 50000]);

        $quote = $this->pricing()->quote([['variant' => $a, 'quantity' => 1]], $coupon, $this->area);

        $this->assertNull($quote->coupon);
        $this->assertSame(0, $quote->discountTotal);
        $this->assertSame('This code needs an order of at least 500.00 EGP.', $quote->couponError);
    }
}
