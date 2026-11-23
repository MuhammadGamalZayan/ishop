<?php

return [
    'errors' => [
        'empty_cart' => 'Your cart is empty. If you just placed an order, you can find it in My orders.',
        'unavailable' => ':product is no longer available. Remove it from your cart to continue.',
        'out_of_stock' => ':product just sold out. Remove it from your cart to continue.',
        'only_left' => 'Only :count of :product left. Lower the quantity to continue.',
        'a_product' => 'A product',
        'shipping_area' => 'We don\'t deliver to this area at the moment. Choose another area.',
        'address' => 'Choose a delivery address.',
        'payment_method' => 'This payment method is not available.',
        'price_changed' => 'Some prices changed since you opened checkout. Check the new total and confirm again.',
        'key_reused' => 'This checkout session has expired. Reload the page and try again.',
        'transition' => 'An order can\'t go from :from to :to.',
    ],
    'coupon' => [
        'not_found' => 'This code isn\'t valid.',
        'not_started' => 'This code isn\'t active yet.',
        'expired' => 'This code has expired.',
        'min_subtotal' => 'This code needs an order of at least :amount.',
        'used_up' => 'This code has reached its usage limit.',
        'already_used' => 'You have already used this code.',
    ],
    'statuses' => [
        'pending' => 'Awaiting confirmation',
        'confirmed' => 'Confirmed',
        'processing' => 'Being prepared',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ],
];
