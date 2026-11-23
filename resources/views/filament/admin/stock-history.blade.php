{{--
    Latest stock movements of one variant, newest first.
    Inline styles with Filament's colour variables: custom Tailwind classes are
    not in Filament's compiled CSS. Blade escapes every value.
--}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\StockMovement> $movements */
    $muted = 'color: var(--gray-500)';
    $cell = 'padding: 8px 16px 8px 0';
@endphp

@if ($movements->isEmpty())
    <p style="{{ $muted }}; font-size: 0.875rem">{{ __('admin.stock.empty') }}</p>
@else
    <div style="overflow-x: auto">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem">
            <thead>
                <tr style="border-bottom: 1px solid var(--gray-200); {{ $muted }}">
                    <th style="{{ $cell }}; text-align: start; font-weight: 500">{{ __('admin.stock.columns.date') }}</th>
                    <th style="{{ $cell }}; text-align: start; font-weight: 500">{{ __('admin.stock.columns.type') }}</th>
                    <th style="{{ $cell }}; text-align: end; font-weight: 500">{{ __('admin.stock.columns.change') }}</th>
                    <th style="{{ $cell }}; text-align: end; font-weight: 500">{{ __('admin.stock.columns.after') }}</th>
                    <th style="{{ $cell }}; text-align: start; font-weight: 500">{{ __('admin.stock.columns.by') }}</th>
                    <th style="padding: 8px 0; text-align: start; font-weight: 500">{{ __('admin.stock.note') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr style="border-bottom: 1px solid var(--gray-100)">
                        <td style="{{ $cell }}; white-space: nowrap" title="{{ $movement->created_at }}">{{ $movement->created_at?->diffForHumans() }}</td>
                        <td style="{{ $cell }}">{{ __('admin.stock.types.'.$movement->type->value) }}</td>
                        <td style="{{ $cell }}; text-align: end; font-family: ui-monospace, monospace; color: {{ $movement->quantity > 0 ? 'var(--success-600)' : 'var(--danger-600)' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                        <td style="{{ $cell }}; text-align: end; font-family: ui-monospace, monospace">{{ $movement->stock_after }}</td>
                        <td style="{{ $cell }}">{{ $movement->user?->name ?? __('admin.stock.system') }}</td>
                        <td style="padding: 8px 0; {{ $muted }}">{{ $movement->note }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
