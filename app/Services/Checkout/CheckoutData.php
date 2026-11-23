<?php

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;

/**
 * What the checkout form sends, already validated by its FormRequest.
 * Either $addressId (a saved address) or $address (typed in) is set.
 */
final readonly class CheckoutData
{
    /**
     * @param  array{first_name: string, last_name: string, phone: string, shipping_area_id: int, street: string, building: string, floor?: ?string, apartment?: ?string, landmark?: ?string}|null  $address
     */
    public function __construct(
        public string $idempotencyKey,
        public PaymentMethod $paymentMethod,
        public ?int $addressId = null,
        public ?array $address = null,
        public bool $saveAddress = false,
        public ?string $customerNote = null,
        public ?string $couponCode = null,
        public ?int $expectedTotal = null,
    ) {}
}
