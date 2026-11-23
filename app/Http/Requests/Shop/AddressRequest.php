<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A delivery address, typed at checkout or in My account.
 * The area must be one we deliver to right now (active, in an active governorate).
 */
class AddressRequest extends FormRequest
{
    /** Egyptian mobile: 010, 011, 012, 015 + 8 digits. */
    public const PHONE = '/^01[0125]\d{8}$/';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // People type spaces and dashes in phone numbers; store digits only.
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => preg_replace('/\D+/', '', $this->input('phone'))]);
        }
    }

    /** @return array<string, mixed> */
    public static function fields(string $prefix = ''): array
    {
        return [
            $prefix.'label' => ['nullable', 'string', 'max:50'],
            $prefix.'first_name' => ['required', 'string', 'max:100'],
            $prefix.'last_name' => ['required', 'string', 'max:100'],
            $prefix.'phone' => ['required', 'string', 'regex:'.self::PHONE],
            $prefix.'shipping_area_id' => ['required', 'integer', Rule::exists('shipping_areas', 'id')->where('is_active', true)],
            $prefix.'street' => ['required', 'string', 'max:255'],
            $prefix.'building' => ['required', 'string', 'max:50'],
            $prefix.'floor' => ['nullable', 'string', 'max:20'],
            $prefix.'apartment' => ['nullable', 'string', 'max:20'],
            $prefix.'landmark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::fields();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => __('shop.checkout.phone_invalid')];
    }
}
