<?php

namespace App\Services\Checkout;

use App\Models\Coupon;
use App\Models\ShippingArea;

/**
 * A priced cart: what the customer will pay, line by line. All amounts are
 * piastres and tax-inclusive (tax_total is the VAT already inside them).
 */
final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public int $discountTotal,
        public int $shippingTotal,
        public int $taxTotal,
        public int $grandTotal,
        public ?Coupon $coupon,
        public ?string $couponError,
        public ?ShippingArea $shippingArea,
    ) {}

    public function itemCount(): int
    {
        return array_sum(array_map(fn (QuoteLine $l): int => $l->quantity, $this->lines));
    }

    /** @return array<string, mixed> for Inertia / JSON */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (QuoteLine $l): array => $l->toArray(), $this->lines),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'shipping_total' => $this->shippingTotal,
            'tax_total' => $this->taxTotal,
            'grand_total' => $this->grandTotal,
            'coupon' => $this->coupon ? ['code' => $this->coupon->code, 'type' => $this->coupon->type->value] : null,
            'coupon_error' => $this->couponError,
            'shipping_area_id' => $this->shippingArea?->getKey(),
        ];
    }
}
