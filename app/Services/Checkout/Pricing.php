<?php

namespace App\Services\Checkout;

use App\Enums\CouponType;
use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ShippingArea;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns cart lines into a Quote. Pure arithmetic on what it is given: the
 * caller decides which rows are locked. Integer piastres throughout.
 *
 * Rules (agreed 2026-11-23):
 * - subtotal = Σ unit price × quantity (prices include VAT);
 * - a coupon discounts the goods only; free-shipping coupons zero the shipping;
 * - the discount is spread over the lines in proportion to their value
 *   (largest remainder, so the parts always add up exactly);
 * - VAT is extracted from the goods after discount AND from the shipping;
 * - grand total = subtotal − discount + shipping.
 */
class Pricing
{
    public function __construct(private readonly CouponRules $coupons) {}

    /**
     * @param  list<array{variant: ProductVariant, quantity: int}>  $items
     */
    public function quote(array $items, ?Coupon $coupon = null, ?ShippingArea $area = null, ?User $user = null, bool $lockingReads = false): Quote
    {
        // Callers normally eager load these; make sure, without one query per line.
        (new Collection(array_column($items, 'variant')))
            ->loadMissing(['product', 'attributeValues.productAttribute']);

        $subtotals = array_map(fn (array $i): int => $i['variant']->price * $i['quantity'], $items);
        $subtotal = array_sum($subtotals);

        $couponError = null;
        if ($coupon !== null) {
            try {
                $this->coupons->assertUsable($coupon, $subtotal, $user, $lockingReads);
            } catch (CouponException $e) {
                $couponError = $e->getMessage();
                $coupon = null;
            }
        }

        $discount = $coupon ? $this->coupons->discountFor($coupon, $subtotal) : 0;
        $discounts = $this->spread($discount, $subtotals);

        $shipping = $area?->shipping_price ?? 0;
        if ($coupon?->type === CouponType::FreeShipping) {
            $shipping = 0;
        }

        $tax = TaxRate::query()->where('is_active', true)->orderBy('id')->first();

        $lines = [];
        $taxTotal = 0;
        foreach ($items as $i => $item) {
            $lineTotal = $subtotals[$i] - $discounts[$i];
            $lineTax = $tax?->taxIncludedIn($lineTotal) ?? 0;
            $taxTotal += $lineTax;

            $lines[] = new QuoteLine(
                variant: $item['variant'],
                quantity: $item['quantity'],
                unitPrice: $item['variant']->price,
                lineSubtotal: $subtotals[$i],
                discount: $discounts[$i],
                tax: $lineTax,
                lineTotal: $lineTotal,
                options: $this->options($item['variant']),
            );
        }

        $taxTotal += $tax?->taxIncludedIn($shipping) ?? 0;

        return new Quote(
            lines: $lines,
            subtotal: $subtotal,
            discountTotal: $discount,
            shippingTotal: $shipping,
            taxTotal: $taxTotal,
            grandTotal: $subtotal - $discount + $shipping,
            coupon: $coupon,
            couponError: $couponError,
            shippingArea: $area,
        );
    }

    /**
     * Split $amount over $weights proportionally; parts are integers that sum to $amount.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public function spread(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($amount === 0 || $total === 0) {
            return array_fill(0, count($weights), 0);
        }

        $parts = [];
        $remainders = [];
        foreach ($weights as $i => $weight) {
            $exact = $amount * $weight;
            $parts[$i] = intdiv($exact, $total);
            $remainders[$i] = $exact % $total;
        }

        arsort($remainders);
        $left = $amount - array_sum($parts);
        foreach (array_keys($remainders) as $i) {
            if ($left-- <= 0) {
                break;
            }
            $parts[$i]++;
        }

        ksort($parts);

        return array_values($parts);
    }

    /** @return array<string, array<string, string>> attribute name (en) => value translations */
    private function options(ProductVariant $variant): array
    {
        $options = [];

        // In the admin's option order (Colour before Size), like everywhere else.
        foreach ($variant->attributeValues->sortBy(fn (ProductAttributeValue $v): int => $v->productAttribute->sort_order) as $value) {
            /** @var ProductAttributeValue $value */
            $options[(string) $value->productAttribute->getTranslation('name', 'en', false)] = [
                'name' => $value->productAttribute->getTranslations('name'),
                'value' => $value->getTranslations('value'),
            ];
        }

        return $options;
    }
}
