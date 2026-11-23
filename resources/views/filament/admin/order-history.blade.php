{{--
    Status changes, newest first. Inline styles (see order-items.blade.php).
    Blade escapes every value; comments are typed by staff.
--}}
@php
    /** @var \App\Models\Order $order */
    $order = $getRecord();
    $history = $order->statusHistory()->with('user:id,name')->latest('id')->get();
    $muted = 'color: var(--gray-500)';
@endphp

<ol style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; border-inline-start: 2px solid var(--gray-200); padding-inline-start: 18px">
    @foreach ($history as $entry)
        <li>
            <div style="font-size: 0.875rem">
                @if ($entry->from_status)
                    <span style="{{ $muted }}">{{ __('checkout.statuses.'.$entry->from_status->value) }}</span>
                    <span aria-hidden="true">→</span>
                @endif
                <span style="font-weight: 600">{{ __('checkout.statuses.'.$entry->to_status->value) }}</span>
            </div>
            <div style="{{ $muted }}; font-size: 0.75rem">
                <span title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</span>
                ·
                {{ $entry->user?->name ?? __('admin.stock.system') }}
                @if ($entry->from_status === null)
                    ({{ __('admin.orders.history.customer') }})
                @endif
            </div>
            @if ($entry->comment)
                <p style="margin: 6px 0 0; padding: 8px 12px; border-radius: 6px; background: var(--gray-100); color: var(--gray-800); font-size: 0.875rem">{{ $entry->comment }}</p>
            @endif
        </li>
    @endforeach
</ol>
