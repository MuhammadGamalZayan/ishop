import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { useT } from '@/lib/shop';

export type HeroSlide = { type: 'image' | 'video'; image: string | null; video: string | null; kicker: string; title: string; button: string; url: string };

/**
 * Full-width hero: photos and muted looping videos that cross-fade every
 * 7 seconds. Pauses while hovered or focused and for reduced motion; only
 * the visible video plays.
 */
export function HeroSlider({ slides }: { slides: HeroSlide[] }) {
    const t = useT();
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const videos = useRef<(HTMLVideoElement | null)[]>([]);

    useEffect(() => {
        if (slides.length < 2 || paused || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const timer = window.setTimeout(() => setIndex((i) => (i + 1) % slides.length), 7000);

        return () => window.clearTimeout(timer);
    }, [index, paused, slides.length]);

    useEffect(() => {
        videos.current.forEach((v, i) => {
            if (!v) return;
            if (i === index) void v.play().catch(() => {});
            else v.pause();
        });
    }, [index]);

    if (slides.length === 0) return null;

    return (
        <section
            aria-roledescription="carousel"
            className="relative h-[78vh] min-h-[460px] overflow-hidden bg-[#2b2724] lg:h-[calc(100vh-80px)] lg:min-h-[560px]"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
        >
            {slides.map((slide, i) => (
                <div
                    key={i}
                    aria-hidden={i !== index}
                    aria-label={t('home.slide', { n: i + 1 })}
                    className={cn('absolute inset-0 transition-opacity duration-1000', i === index ? 'opacity-100' : 'pointer-events-none opacity-0')}
                >
                    {slide.type === 'video' && slide.video ? (
                        <video
                            ref={(el) => {
                                videos.current[i] = el;
                            }}
                            src={slide.video}
                            poster={slide.image ?? undefined}
                            muted
                            loop
                            playsInline
                            preload={i === 0 ? 'auto' : 'metadata'}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        slide.image && <img src={slide.image} alt="" loading={i === 0 ? 'eager' : 'lazy'} className={cn('h-full w-full object-cover transition-transform duration-[7000ms] ease-out', i === index ? 'scale-105' : 'scale-100')} />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-black/15 to-black/40" />

                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-5 px-6 text-center text-white">
                        {slide.kicker && <p className={cn('store-caps text-xs transition-all delay-300 duration-700', i === index ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0')}>{slide.kicker}</p>}
                        {slide.title && (
                            <h2 className={cn('text-[clamp(30px,4.4vw,52px)] font-light uppercase transition-all delay-500 duration-700 ltr:tracking-[0.16em]', i === index ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0')}>
                                {slide.title}
                            </h2>
                        )}
                        {slide.button && (
                            <Link
                                href={slide.url}
                                tabIndex={i === index ? 0 : -1}
                                className={cn('store-caps mt-1 inline-flex h-12 items-center bg-white px-8 text-[13px] text-ink transition-all delay-700 duration-700 hover:bg-ink hover:text-white', i === index ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0')}
                            >
                                {slide.button}
                            </Link>
                        )}
                    </div>
                </div>
            ))}

            {slides.length > 1 && (
                <div className="absolute inset-x-0 bottom-7 flex justify-center gap-2.5">
                    {slides.map((_, i) => (
                        <button key={i} type="button" onClick={() => setIndex(i)} aria-label={t('home.slide', { n: i + 1 })} aria-current={i === index} className="flex h-6 items-center">
                            <span className={cn('block h-0.5 w-9 transition-colors', i === index ? 'bg-white' : 'bg-white/45')} />
                        </button>
                    ))}
                </div>
            )}
        </section>
    );
}
