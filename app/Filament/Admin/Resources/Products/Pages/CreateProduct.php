<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\Catalog\VariantGenerator;
use App\Services\Inventory;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array{price: int, compare_at_price: ?int, sku: ?string, stock: int} */
    private array $first = ['price' => 0, 'compare_at_price' => null, 'sku' => null, 'stock' => 0];

    /** @var array<int, list<int>> attribute id => value ids */
    private array $options = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $raw = $this->form->getRawState();

        $this->first = [
            'price' => (int) ($data['first']['price'] ?? 0),
            'compare_at_price' => $data['first']['compare_at_price'] ?? null,
            'sku' => filled($data['first']['sku'] ?? null) ? strtoupper($data['first']['sku']) : null,
            'stock' => (int) ($data['first']['stock'] ?? 0),
        ];

        $this->options = ! empty($raw['has_options'])
            ? array_filter(array_map(fn ($ids) => array_map('intval', (array) $ids), (array) ($raw['options'] ?? [])))
            : [];

        if (! empty($raw['has_options']) && $this->options === []) {
            throw ValidationException::withMessages(['data.has_options' => __('admin.products.errors.choose_options')]);
        }

        unset($data['first'], $data['options'], $data['has_options']);

        if (($data['status'] ?? null) === ProductStatus::Active->value && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return TranslatableFields::clean($data, (new Product)->getTranslatableAttributes());
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Product {
            /** @var Product $product */
            $product = parent::handleRecordCreation($data);

            if ($this->options !== []) {
                try {
                    $created = app(VariantGenerator::class)->generate($product, $this->options, $this->first['price'], $this->first['sku']);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['data.has_options' => $e->getMessage()]);
                }

                $created->each(fn ($variant) => $variant->update(['compare_at_price' => $this->first['compare_at_price']]));

                return $product;
            }

            $variant = $product->variants()->create([
                'sku' => $this->first['sku'] ?? strtoupper(Str::limit($product->slug, 40, '')).'-'.$product->getKey(),
                'price' => $this->first['price'],
                'compare_at_price' => $this->first['compare_at_price'],
                'is_default' => true,
                'is_active' => true,
                'track_inventory' => true,
            ]);

            if ($this->first['stock'] > 0) {
                app(Inventory::class)->adjust($variant, $this->first['stock'], StockMovementType::Restock, auth()->user(), __('admin.products.initial_stock_note'));
            }

            return $product;
        });
    }

    protected function afterCreate(): void
    {
        if ($this->options !== []) {
            Notification::make()
                ->success()
                ->title(__('admin.products.notifications.variants_generated', ['count' => $this->getRecord()->variants()->count()]))
                ->body(__('admin.products.notifications.set_stock'))
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
