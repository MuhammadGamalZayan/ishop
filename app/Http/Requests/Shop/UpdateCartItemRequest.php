<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the line must belong to the shopper's cart: checked by CartService
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:'.config('ishop.max_line_quantity')],
        ];
    }
}
