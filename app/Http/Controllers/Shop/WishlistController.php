<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Cart\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function toggle(Request $request, WishlistService $wishlist, int $product): RedirectResponse|JsonResponse
    {
        $saved = $wishlist->toggle($product);

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $saved ? __('cart.wishlist.added') : __('cart.wishlist.removed'),
        ]);

        return back();
    }
}
