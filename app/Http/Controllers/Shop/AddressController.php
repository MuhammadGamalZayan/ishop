<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddressRequest;
use App\Models\Address;
use App\Models\Governorate;
use App\Models\ShippingArea;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** My account → Addresses. Every action is scoped to the signed-in customer's own addresses. */
class AddressController extends Controller
{
    public const MAX = 20;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('shop/account/addresses', [
            'addresses' => $user->addresses()->with(['governorate', 'shippingArea'])->latest('is_default')->latest('id')->get()->map(fn (Address $a): array => [
                'id' => $a->getKey(),
                'label' => $a->label,
                'is_default' => $a->is_default,
                'deliverable' => (bool) ($a->shippingArea?->is_active && $a->governorate?->is_active),
                'fields' => $a->only(['label', 'first_name', 'last_name', 'phone', 'shipping_area_id', 'street', 'building', 'floor', 'apartment', 'landmark']) + ['governorate_id' => $a->governorate_id],
                'area' => $a->shippingArea?->name.', '.$a->governorate?->name,
            ]),
            'governorates' => Governorate::query()->active()->with(['shippingAreas' => fn ($q) => $q->active()])->get()
                ->filter(fn (Governorate $g): bool => $g->shippingAreas->isNotEmpty())
                ->map(fn (Governorate $g): array => [
                    'id' => $g->getKey(),
                    'name' => $g->name,
                    'areas' => $g->shippingAreas->map(fn (ShippingArea $a): array => ['id' => $a->getKey(), 'name' => $a->name, 'price' => $a->shipping_price])->values(),
                ])->values(),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->addresses()->count() >= self::MAX) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('shop.address.too_many')]);

            return back();
        }

        $user->addresses()->create($this->fields($request) + ['is_default' => ! $user->addresses()->exists()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.address.saved')]);

        return back();
    }

    public function update(AddressRequest $request, int $address): RedirectResponse
    {
        $this->own($request, $address)->update($this->fields($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.address.saved')]);

        return back();
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $model = $this->own($request, $address);

        DB::transaction(function () use ($request, $model): void {
            $model->delete();

            if ($model->is_default) {
                $request->user()->addresses()->latest('id')->first()?->update(['is_default' => true]);
            }
        });

        return back();
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        $model = $this->own($request, $address);

        DB::transaction(function () use ($request, $model): void {
            $request->user()->addresses()->update(['is_default' => false]);
            $model->update(['is_default' => true]);
        });

        return back();
    }

    private function own(Request $request, int $id): Address
    {
        return $request->user()->addresses()->whereKey($id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function fields(AddressRequest $request): array
    {
        $data = $request->validated();
        $data['governorate_id'] = ShippingArea::query()->whereKey($data['shipping_area_id'])->value('governorate_id');

        return $data;
    }
}
