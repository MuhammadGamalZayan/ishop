<?php

namespace App\Http\Requests\Shop;

use App\Services\Payments\StripePayments;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('address.phone'))) {
            $address = $this->input('address');
            $address['phone'] = preg_replace('/\D+/', '', $address['phone']);
            $this->merge(['address' => $address]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $newAddress = $this->input('address_id') === null;

        return [
            'idempotency_key' => ['required', 'uuid'],
            'payment_method' => ['required', StripePayments::enabled() ? 'in:cod,stripe' : 'in:cod'],
            'address_id' => ['nullable', 'integer'],
            'save_address' => ['boolean'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'expected_total' => ['required', 'integer', 'min:0'],
            ...($newAddress ? AddressRequest::fields('address.') : []),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['address.phone.regex' => __('shop.checkout.phone_invalid')];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(['first_name', 'last_name', 'phone', 'shipping_area_id', 'street', 'building', 'floor', 'apartment', 'landmark'])
            ->mapWithKeys(fn (string $f): array => ["address.{$f}" => __("shop.address.{$f}")])
            ->all();
    }
}
