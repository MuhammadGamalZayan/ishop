import { useRef, useState } from 'react';
import { ProductImage } from '@/components/shop/product-image';
import { useShop, useT } from '@/lib/shop';

type Image = { large: string; thumb: string };

/**
 * Product photos.
 * - Desktop (lg and up): every photo stacked in a column; the page scrolls
 *   through them while the details panel beside them stays in place.
 * - Phone / tablet: a swipe slider (CSS scroll-snap, no library) with dots.
 * With no photos yet, one placeholder tile.
 */
export function ProductGallery({ images, alt, swatch }: { images: Image[]; alt: string; swatch: string | null }) {
    const t = useT();
    const { dir } = useShop();
    const track = useRef<HTMLDivElement>(null);
    const [index, setIndex] = useState(0);
    const slides = images.length > 0 ? images : [null];

    const onScroll = () => {
        const el = track.current;
        if (!el) return;
        // scrollLeft is negative in RTL in modern browsers: use its size only.
        setIndex(Math.round(Math.abs(el.scrollLeft) / el.clientWidth));
    };

    const goTo = (i: number) => {
        const el = track.current;
        if (!el) return;
        el.scrollTo({ left: (dir === 'rtl' ? -1 : 1) * i * el.clientWidth, behavior: 'smooth' });
    };

    return (
        <div className="min-w-0">
            {/* Phone / tablet slider */}
            <div className="lg:hidden">
                <div
                    ref={track}
                    onScroll={onScroll}
                    role="region"
                    aria-roledescription="carousel"
                    aria-label={t('pdp.gallery')}
                    className="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    {slides.map((image, i) => (
                        <div key={image?.large ?? 'placeholder'} className="aspect-square w-full flex-none snap-center" aria-label={t('pdp.image', { n: i + 1 })}>
                            <ProductImage src={image?.large ?? null} alt={i === 0 ? alt : ''} swatch={swatch} sizes="100vw" eager={i === 0} />
                        </div>
                    ))}
                </div>
                {slides.length > 1 && (
                    <div className="mt-3 flex justify-center gap-2">
                        {slides.map((_, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => goTo(i)}
                                aria-label={t('pdp.image', { n: i + 1 })}
                                aria-current={i === index}
                                className="flex size-6 items-center justify-center"
                            >
                                <span className={`block size-2 rounded-full transition ${i === index ? 'bg-ink' : 'bg-[#c4c4c4]'}`} />
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Desktop column */}
            <div className="hidden flex-col gap-4 lg:flex" aria-label={t('pdp.gallery')}>
                {slides.map((image, i) => (
                    <div key={image?.large ?? 'placeholder'} className="aspect-square w-full">
                        <ProductImage src={image?.large ?? null} alt={i === 0 ? alt : ''} swatch={swatch} sizes="(min-width: 1024px) 55vw, 100vw" eager={i === 0} />
                    </div>
                ))}
            </div>
        </div>
    );
}
