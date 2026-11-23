import { ImageOff } from 'lucide-react';
import { useT } from '@/lib/shop';
import { cn } from '@/lib/utils';

/**
 * A product photo (WebP from the media library), or a neutral placeholder
 * until one is uploaded in the admin panel. The placeholder takes a soft
 * tint of the product's colour so cards of different colours still differ.
 */
export function ProductImage({
    src,
    hoverSrc,
    alt,
    swatch,
    className,
    sizes = '(min-width: 1024px) 25vw, 50vw',
    compact = false,
    eager = false,
}: {
    src: string | null;
    hoverSrc?: string | null;
    alt: string;
    swatch?: string | null;
    className?: string;
    sizes?: string;
    /** Small thumbnails: icon only, no text. */
    compact?: boolean;
    /** The first, visible image of a page: load it right away. */
    eager?: boolean;
}) {
    const t = useT();

    if (src) {
        return (
            <span className={cn('relative block h-full w-full', className)}>
                <img src={src} alt={alt} sizes={sizes} loading={eager ? 'eager' : 'lazy'} decoding="async" className="h-full w-full object-contain" />
                {hoverSrc && (
                    <img
                        src={hoverSrc}
                        alt=""
                        aria-hidden="true"
                        loading="lazy"
                        className="absolute inset-0 h-full w-full object-contain opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    />
                )}
            </span>
        );
    }

    return (
        <span
            role="img"
            aria-label={alt}
            className={cn('flex h-full w-full flex-col items-center justify-center gap-2 bg-[#e8e6e3] text-[#8a8580]', className)}
            // A light wash of the product colour over the neutral tile.
            style={swatch ? { backgroundImage: `linear-gradient(${swatch}26, ${swatch}26)` } : undefined}
        >
            <ImageOff className={compact ? 'size-4' : 'size-7'} strokeWidth={1.2} aria-hidden="true" />
            {!compact && <span className="text-[11px] tracking-wide">{t('image_soon')}</span>}
        </span>
    );
}
