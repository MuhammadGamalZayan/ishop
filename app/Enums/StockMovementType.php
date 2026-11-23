<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Restock = 'restock';
    case Sale = 'sale';
    case Cancellation = 'cancellation';
    case Return = 'return';
    case Adjustment = 'adjustment';
}
