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
            $this->merge(['phone' => self::normalizePhone($this->input('phone'))]);
        }
    }

    /**
     * Digits only, with Arabic-Indic (٠-٩) and Persian (۰-۹) digits turned into
     * 0-9 first: Arabic keyboards type those, and \D would delete them.
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        return (string) preg_replace('/\D+/', '', $phone);
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
