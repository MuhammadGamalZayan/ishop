<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared image setup for products and variants: one `images` gallery,
 * converted on the queue into WebP sizes used by the storefront.
 */
trait HasProductImages
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Contain, 200, 200)->format('webp');
        $this->addMediaConversion('card')->fit(Fit::Contain, 600, 600)->format('webp');
        $this->addMediaConversion('large')->fit(Fit::Max, 1400, 1400)->format('webp');
    }
}
