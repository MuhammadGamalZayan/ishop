<?php

namespace App\Filament\Admin\Resources\Coupons\Pages;

use App\Filament\Admin\Resources\Coupons\CouponResource;
use App\Models\Coupon;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription(__('admin.coupons.help.delete')),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->getRecord();

        return CouponResource::valueToForm($coupon, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CouponResource::valueFromForm($data, $this->form->getRawState());
    }
}
