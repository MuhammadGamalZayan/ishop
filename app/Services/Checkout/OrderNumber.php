<?php

namespace App\Services\Checkout;

use App\Models\Order;

/**
 * IS-YYMMDD-XXXXX: date plus 5 random characters from an alphabet without
 * look-alikes (no 0/O, 1/I/L). Not sequential, so it reveals nothing about
 * order volume and can't be guessed. 31^5 ≈ 28.6 million per day.
 */
final class OrderNumber
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        do {
            $number = 'IS-'.now()->format('ymd').'-'.self::random(5);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    private static function random(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
