<?php

namespace App\Services\Checkout;

use App\Models\ProductVariant;

final readonly class QuoteLine
{
    /**
     * @param  array<string, array<string, string>>  $options  e.g. ['Colour' => ['en' => 'Black', 'ar' => 'أسود']]
     */
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
        public int $unitPrice,
        public int $lineSubtotal,
        public int $discount,
        public int $tax,
        public int $lineTotal,
        public array $options,
    ) {}

    public function toArray(): array
    {
        return [
            'variant_id' => $this->variant->getKey(),
            'product_id' => $this->variant->product_id,
            'name' => $this->variant->product->name,
            'sku' => $this->variant->sku,
            'options' => $this->options,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'line_total' => $this->lineTotal,
            'discount' => $this->discount,
        ];
    }
}
