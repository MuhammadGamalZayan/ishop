<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guests may shop; ownership is enforced by CartService
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.config('ishop.max_line_quantity')],
        ];
    }
}
