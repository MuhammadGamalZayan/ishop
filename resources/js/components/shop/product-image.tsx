import { cn } from '@/lib/utils';

/**
 * A product photo, or until one is uploaded in the admin panel, the bag
 * silhouette from the mockups tinted with the product's colour.
 */
export function ProductImage({
    src,
    hoverSrc,
    alt,
    swatch,
    className,
    sizes = '(min-width: 1024px) 25vw, 50vw',
}: {
    src: string | null;
    hoverSrc?: string | null;
    alt: string;
    swatch?: string | null;
    className?: string;
    sizes?: string;
}) {
    if (src) {
        return (
            <span className={cn('relative block h-full w-full', className)}>
                <img src={src} alt={alt} sizes={sizes} loading="lazy" decoding="async" className="h-full w-full object-contain" />
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

    const colour = swatch ?? '#3a3a3a';

    return (
        <span className={cn('flex h-full w-full items-center justify-center', className)}>
            <svg viewBox="0 0 200 160" className="h-auto w-[62%]" role="img" aria-label={alt}>
                <path d="M72 62 V44 a28 28 0 0 1 56 0 V62" fill="none" stroke={colour} strokeWidth="7" />
                <rect x="28" y="58" width="144" height="92" rx="8" fill={colour} />
                <rect x="92" y="74" width="16" height="10" rx="2" fill="#c9a96a" />
            </svg>
        </span>
    );
}
