<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** My account → My orders and the order page with tracking. Customers see only their own orders. */
class AccountController extends Controller
{
    public function orders(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $page = $user->orders()
            ->with(['items' => fn ($q) => $q->with('variant.product.media')])
            ->latest('placed_at')
            ->paginate(10);

        return Inertia::render('shop/account/orders', [
            'orders' => collect($page->items())->map(fn (Order $o): array => [
                'number' => $o->number,
                'url' => route('shop.account.order', ['number' => $o->number]),
                'status' => $o->status->value,
                'status_label' => __("checkout.statuses.{$o->status->value}"),
                'placed_at' => $o->placed_at?->translatedFormat('j M Y'),
                'items' => trans_choice('shop.account.items', (int) $o->items->sum('quantity'), ['count' => (int) $o->items->sum('quantity')]),
                'total' => $o->grand_total,
                'images' => $o->items->take(3)->map(fn (OrderItem $i): ?string => $i->variant?->product?->getFirstMediaUrl('images', 'thumb') ?: null)->values(),
            ]),
            'pagination' => ['prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
        ]);
    }

    public function order(Request $request, string $number): Response
    {
        $order = $this->own($request, $number);
        $order->load(['items', 'address', 'statusHistory']);
        $reached = $order->statusHistory->keyBy(fn (OrderStatusHistory $h): string => $h->to_status->value);
        $steps = [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered];
        $a = $order->address;

        return Inertia::render('shop/account/order', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => __("checkout.statuses.{$order->status->value}"),
                'placed_at' => $order->placed_at?->translatedFormat('j M Y, H:i'),
                'can_cancel' => $order->status === OrderStatus::Pending,
                'payment' => __("admin.orders.methods.{$order->payment_method->value}").' · '.__("admin.orders.payment_statuses.{$order->payment_status->value}"),
                'timeline' => in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)
                    ? []
                    : collect($steps)->map(fn (OrderStatus $s): array => [
                        'label' => __("shop.account.steps.{$s->value}"),
                        'done' => $reached->has($s->value),
                        'date' => $reached->get($s->value)?->created_at?->translatedFormat('j M'),
                    ])->all(),
                'latest_note' => $order->statusHistory->sortByDesc('id')->first(fn (OrderStatusHistory $h): bool => filled($h->comment) && $h->user_id !== $order->user_id && $h->to_status === $order->status)?->comment,
                'items' => $order->items->map(fn (OrderItem $i): array => [
                    'name' => $i->product_name,
                    'options' => collect($i->options ?? [])->map(fn ($o) => $o['value'][app()->getLocale()] ?? reset($o['value']))->implode(' / '),
                    'quantity' => $i->quantity,
                    'total' => $i->line_total,
                ]),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount_total,
                'shipping' => $order->shipping_total,
                'total' => $order->grand_total,
                'tax' => $order->tax_total,
                'coupon' => $order->coupon_code,
                'note' => $order->customer_note,
                'address' => $a ? collect([
                    trim($a->first_name.' '.$a->last_name),
                    $a->street.', '.__('shop.address.building_short', ['value' => $a->building])
                        .($a->floor ? ', '.__('shop.address.floor_short', ['value' => $a->floor]) : '')
                        .($a->apartment ? ', '.__('shop.address.apartment_short', ['value' => $a->apartment]) : ''),
                    $a->landmark,
                    $a->area_name.', '.$a->governorate_name,
                    $a->phone,
                ])->filter()->values() : [],
            ],
        ]);
    }

    /** A customer may cancel their own order only while it waits for confirmation. */
    public function cancel(Request $request, string $number, OrderWorkflow $workflow): RedirectResponse
    {
        $order = $this->own($request, $number);

        if ($order->status !== OrderStatus::Pending) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('shop.account.cannot_cancel')]);

            return back();
        }

        try {
            $workflow->transition($order, OrderStatus::Cancelled, $request->user(), __('shop.account.cancelled_by_customer'));
        } catch (InvalidOrderTransitionException) {
            // Staff confirmed it a moment ago.
            Inertia::flash('toast', ['type' => 'error', 'message' => __('shop.account.cannot_cancel')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.account.cancelled')]);

        return back();
    }

    /** The order, only if it is this customer's (404 otherwise: never reveal other numbers exist). */
    private function own(Request $request, string $number): Order
    {
        return Order::query()->where('number', $number)->where('user_id', $request->user()->getKey())->firstOrFail();
    }
}
