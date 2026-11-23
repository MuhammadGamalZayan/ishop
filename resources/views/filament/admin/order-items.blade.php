{{--
    Order lines and totals, from the order's own snapshots.
    Inline styles with Filament's colour variables: custom Tailwind classes are
    not in Filament's compiled CSS. Blade escapes every value.
--}}
@php
    /** @var \App\Models\Order $order */
    $order = $getRecord();
    $order->loadMissing('items');
    $locale = app()->getLocale();
    $money = fn (int $piastres): string => \App\Services\Checkout\Money::format($piastres);
    $muted = 'color: var(--gray-500)';
    $line = 'border-bottom: 1px solid var(--gray-200)';
@endphp

<div style="overflow-x: auto">
    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem">
        <thead>
            <tr style="{{ $line }}; {{ $muted }}">
                <th style="padding: 8px 16px 8px 0; text-align: start; font-weight: 500">{{ __('admin.orders.items.product') }}</th>
                <th style="padding: 8px 16px 8px 0; text-align: end; font-weight: 500">{{ __('admin.orders.items.unit_price') }}</th>
                <th style="padding: 8px 16px 8px 0; text-align: center; font-weight: 500">{{ __('admin.orders.items.qty') }}</th>
                <th style="padding: 8px 0; text-align: end; font-weight: 500">{{ __('admin.orders.items.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr style="{{ $line }}; vertical-align: top">
                    <td style="padding: 12px 16px 12px 0">
                        <div style="font-weight: 500">{{ $item->getTranslation('product_name', $locale) }}</div>
                        @if ($item->options)
                            <div style="{{ $muted }}">
                                {{ collect($item->options)->map(fn ($o) => ($o['name'][$locale] ?? reset($o['name'])).': '.($o['value'][$locale] ?? reset($o['value'])))->implode(' · ') }}
                            </div>
                        @endif
                        <div style="{{ $muted }}; font-family: ui-monospace, monospace; font-size: 0.75rem">{{ $item->sku }}</div>
                    </td>
                    <td style="padding: 12px 16px 12px 0; text-align: end; white-space: nowrap">{{ $money($item->unit_price) }}</td>
                    <td style="padding: 12px 16px 12px 0; text-align: center">{{ $item->quantity }}</td>
                    <td style="padding: 12px 0; text-align: end; white-space: nowrap">
                        {{ $money($item->line_total) }}
                        @if ($item->discount_amount > 0)
                            <div style="{{ $muted }}; font-size: 0.75rem">{{ __('admin.orders.items.after_discount', ['amount' => $money($item->discount_amount)]) }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<dl style="margin: 16px 0 0 auto; margin-inline-start: auto; margin-inline-end: 0; max-width: 24rem; display: grid; grid-template-columns: 1fr auto; gap: 8px 24px; font-size: 0.875rem">
    <dt style="{{ $muted }}">{{ __('admin.orders.totals.subtotal') }}</dt>
    <dd style="margin: 0; text-align: end">{{ $money($order->subtotal) }}</dd>

    @if ($order->discount_total > 0)
        <dt style="{{ $muted }}">{{ __('admin.orders.totals.discount') }} @if ($order->coupon_code)<span style="font-family: ui-monospace, monospace">({{ $order->coupon_code }})</span>@endif</dt>
        <dd style="margin: 0; text-align: end">−{{ $money($order->discount_total) }}</dd>
    @endif

    <dt style="{{ $muted }}">{{ __('admin.orders.totals.shipping') }}</dt>
    <dd style="margin: 0; text-align: end">{{ $order->shipping_total === 0 ? __('admin.shipping.free') : $money($order->shipping_total) }}</dd>

    <dt style="border-top: 1px solid var(--gray-200); padding-top: 8px; font-weight: 600">{{ __('admin.orders.totals.total') }}</dt>
    <dd style="margin: 0; border-top: 1px solid var(--gray-200); padding-top: 8px; text-align: end; font-weight: 600">{{ $money($order->grand_total) }}</dd>

    <dt style="{{ $muted }}; font-size: 0.75rem">{{ __('admin.orders.totals.vat') }}</dt>
    <dd style="margin: 0; {{ $muted }}; text-align: end; font-size: 0.75rem">{{ $money($order->tax_total) }}</dd>
</dl>
