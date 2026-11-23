<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddToCartRequest;
use App\Http\Requests\Shop\UpdateCartItemRequest;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thin: validation in form requests, rules in CartService, errors become a
 * toast via the CartException handler in bootstrap/app.php. The storefront
 * opens the cart drawer after a successful add (agreed UX: no extra toast).
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function store(AddToCartRequest $request): RedirectResponse|JsonResponse
    {
        $this->cart->add((int) $request->validated('variant_id'), (int) ($request->validated('quantity') ?? 1));

        return $this->respond($request);
    }

    public function update(UpdateCartItemRequest $request, int $item): RedirectResponse|JsonResponse
    {
        $this->cart->setQuantity($item, (int) $request->validated('quantity'));

        return $this->respond($request);
    }

    public function destroy(Request $request, int $item): RedirectResponse|JsonResponse
    {
        $this->cart->remove($item);

        return $this->respond($request);
    }

    private function respond(Request $request): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['cart_count' => $this->cart->count()])
            : back();
    }
}
