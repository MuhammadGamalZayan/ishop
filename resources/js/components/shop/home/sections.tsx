import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AddToCartButton } from '@/components/shop/add-to-cart-button';
import { useCart } from '@/components/shop/cart-context';
import { FadeIn } from '@/components/shop/home/fade-in';
import { ProductCard } from '@/components/shop/product-card';
import { ProductImage } from '@/components/shop/product-image';
import { cn } from '@/lib/utils';
import { money, useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

const Heading = ({ kicker, title }: { kicker?: string; title?: string }) => (
    <div className="flex flex-col items-center gap-3.5 text-center">
        {kicker && <p className="store-caps text-xs text-[#4a4a4a]">{kicker}</p>}
        {title && <h2 className="text-[clamp(24px,3vw,30px)] font-light uppercase ltr:tracking-[0.16em]">{title}</h2>}
    </div>
);

/* ---------------- Best sellers (tabs) ---------------- */

export type ProductTab = { name: string; url: string; products: ProductCardData[] };

export function BestSellers({ data }: { data: { kicker: string; tabs: ProductTab[] } }) {
    const t = useT();
    const [active, setActive] = useState(0);
    const [visible, setVisible] = useState(true);
    const tab = data.tabs[active];

    const pick = (i: number) => {
        if (i === active) return;
        setVisible(false);
        window.setTimeout(() => {
            setActive(i);
            setVisible(true);
        }, 160);
    };

    return (
        <section className="px-4 py-20 sm:px-10 lg:py-24">
            <div className="mx-auto flex max-w-[1360px] flex-col items-center gap-10">
                <FadeIn className="flex flex-col items-center gap-5">
                    <p className="store-caps text-xs text-[#4a4a4a]">{data.kicker}</p>
                    <div role="tablist" className="flex flex-wrap justify-center gap-x-9 gap-y-2">
                        {data.tabs.map((tb, i) => (
                            <button
                                key={tb.url}
                                type="button"
                                role="tab"
                                aria-selected={i === active}
                                onClick={() => pick(i)}
                                className={cn(
                                    'border-b pb-1 text-[clamp(18px,2.4vw,26px)] font-light uppercase transition-colors ltr:tracking-[0.14em]',
                                    i === active ? 'border-ink text-ink' : 'border-transparent text-[#9a9a9a] hover:text-ink',
                                )}
                            >
                                {tb.name}
                            </button>
                        ))}
                    </div>
                </FadeIn>
                <div role="tabpanel" className={cn('grid w-full grid-cols-2 gap-x-4 gap-y-10 transition-opacity duration-200 lg:grid-cols-4 lg:gap-x-7', visible ? 'opacity-100' : 'opacity-0')}>
                    {tab.products.map((p) => (
                        <ProductCard key={p.id} product={p} />
                    ))}
                </div>
                <Link href={tab.url} className="store-caps inline-flex h-12 items-center bg-ink px-8 text-[13px] text-white transition-opacity hover:opacity-85">
                    {t('home.all_best', { name: tab.name })}
                </Link>
            </div>
        </section>
    );
}

/* ---------------- New in ---------------- */

export function ProductRow({ data }: { data: { kicker: string; title: string; url: string; button: string; products: ProductCardData[] } }) {
    return (
        <section className="px-4 pb-20 sm:px-10 lg:pb-24">
            <div className="mx-auto flex max-w-[1360px] flex-col items-center gap-10">
                <FadeIn>
                    <Heading kicker={data.kicker} title={data.title} />
                </FadeIn>
                <FadeIn className="grid w-full grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4 lg:gap-x-7">
                    {data.products.map((p) => (
                        <ProductCard key={p.id} product={p} />
                    ))}
                </FadeIn>
                <Link href={data.url} className="store-caps inline-flex h-12 items-center bg-ink px-8 text-[13px] text-white transition-opacity hover:opacity-85">
                    {data.button}
                </Link>
            </div>
        </section>
    );
}

/* ---------------- Three category pictures ---------------- */

export function CategoryTrio({ items }: { items: { image: string | null; title: string; url: string }[] }) {
    const t = useT();
    const tile = (item: (typeof items)[number], className: string, delay = 0) => (
        <FadeIn delay={delay} className={className}>
            <Link href={item.url} className="group relative block h-full overflow-hidden bg-mist">
                {item.image && <img src={item.image} alt="" loading="lazy" className="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-105" />}
                <span className="absolute inset-0 bg-black/15 transition-colors duration-500 group-hover:bg-black/30" />
                <span className="absolute inset-0 flex flex-col items-center justify-center gap-3 text-white">
                    <span className="text-[clamp(22px,2.4vw,30px)] font-light uppercase ltr:tracking-[0.16em]">{item.title}</span>
                    <span className="store-caps translate-y-1.5 border-b border-white text-xs opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">{t('home.discover')}</span>
                </span>
            </Link>
        </FadeIn>
    );

    return (
        <section className="px-4 pb-20 sm:px-10 lg:pb-24">
            <div className="mx-auto grid max-w-[1360px] grid-cols-2 gap-4 lg:h-[700px] lg:grid-cols-[1.6fr_1fr] lg:grid-rows-2 lg:gap-6">
                {tile(items[0], 'col-span-2 aspect-[4/5] sm:aspect-[16/11] lg:col-span-1 lg:row-span-2 lg:aspect-auto')}
                {tile(items[1], 'aspect-square lg:aspect-auto', 120)}
                {tile(items[2], 'aspect-square lg:aspect-auto', 240)}
            </div>
        </section>
    );
}

/* ---------------- Scrolling text ---------------- */

export function Marquee({ rows }: { rows: { text: string; url: string; image: string | null }[][] }) {
    const { dir } = useShop();

    return (
        <section aria-label="Highlights" className="border-y border-[#dcdcdc]">
            {rows.map((row, r) => {
                // Repeat the items so the strip is always wider than the screen.
                const items = Array.from({ length: 4 }, () => row).flat();
                // Every other line runs the other way; in Arabic both run the mirrored way.
                const reverse = (r % 2 === 1) !== (dir === 'rtl');

                return (
                    // The strip itself is laid out left to right (so the loop is seamless);
                    // each Arabic item still reads right to left.
                    <div key={r} dir="ltr" className={cn('group overflow-hidden py-5', r > 0 && 'border-t border-[#e4e4e4]')}>
                        <div className={cn('flex w-max gap-12 group-hover:[animation-play-state:paused] motion-reduce:animate-none', reverse ? 'animate-[marquee_42s_linear_infinite_reverse]' : 'animate-[marquee_42s_linear_infinite]')}>
                            {items.map((item, i) => (
                                <Link key={i} href={item.url} dir="auto" aria-hidden={i >= row.length} tabIndex={i >= row.length ? -1 : 0} className="flex shrink-0 items-center gap-5 text-[clamp(20px,2.6vw,34px)] font-light whitespace-nowrap text-[#a8a8a8] uppercase transition-colors hover:text-ink ltr:tracking-[0.14em]">
                                    {item.image && <img src={item.image} alt="" loading="lazy" className="h-10 w-14 object-cover" />}
                                    {item.text}
                                </Link>
                            ))}
                        </div>
                    </div>
                );
            })}
        </section>
    );
}

/* ---------------- Video (plays in place) ---------------- */

export function VideoBlock({ data }: { data: { video: string | null; poster: string | null; kicker: string; title: string } }) {
    const t = useT();
    const ref = useRef<HTMLVideoElement>(null);
    const [playing, setPlaying] = useState(false);

    const toggle = () => {
        const v = ref.current;
        if (!v) return;
        if (v.paused) void v.play();
        else v.pause();
    };

    return (
        <FadeIn as="section" className="relative h-[clamp(380px,62vh,640px)] overflow-hidden bg-[#111]">
            <video
                ref={ref}
                src={data.video ?? undefined}
                poster={data.poster ?? undefined}
                playsInline
                preload="none"
                controls={playing}
                onPlay={() => setPlaying(true)}
                onPause={() => setPlaying(false)}
                onEnded={() => setPlaying(false)}
                className="h-full w-full object-cover"
            />
            <div className={cn('absolute inset-0 flex flex-col items-center justify-center gap-5 bg-black/25 px-6 text-center text-white transition-opacity duration-500', playing && 'pointer-events-none opacity-0')}>
                {data.kicker && <p className="store-caps text-xs">{data.kicker}</p>}
                {data.title && <h2 className="text-[clamp(26px,3.4vw,40px)] font-light uppercase ltr:tracking-[0.16em]">{data.title}</h2>}
                <button type="button" onClick={toggle} aria-label={t('home.play')} className="pointer-events-auto flex size-20 items-center justify-center rounded-full bg-white text-ink transition-transform hover:scale-105">
                    <Play className="size-6 fill-current ps-0.5" aria-hidden="true" />
                </button>
            </div>
            {playing && (
                <button type="button" onClick={toggle} aria-label={t('home.pause')} className="absolute end-5 top-5 flex size-11 items-center justify-center rounded-full bg-white/90 text-ink">
                    <Pause className="size-4 fill-current" aria-hidden="true" />
                </button>
            )}
        </FadeIn>
    );
}

/* ---------------- Our looks (photo dots → product) ---------------- */

export type Look = { image: string; hotspots: { x: number; y: number; product: ProductCardData }[] };

export function Looks({ slides }: { slides: Look[] }) {
    const t = useT();
    const { locale } = useShop();
    const cart = useCart();
    const [index, setIndex] = useState(0);
    const [dot, setDot] = useState(0);
    const [fade, setFade] = useState(true);
    const slide = slides[index];
    const product = slide.hotspots[Math.min(dot, slide.hotspots.length - 1)].product;

    const go = (i: number) => {
        setFade(false);
        window.setTimeout(() => {
            setIndex((i + slides.length) % slides.length);
            setDot(0);
            setFade(true);
        }, 180);
    };

    const arrow = 'hidden size-12 shrink-0 items-center justify-center rounded-full bg-white shadow-[0_2px_10px_rgba(0,0,0,0.06)] transition-transform hover:scale-105 lg:flex';

    return (
        <section className="px-4 py-20 sm:px-10 lg:py-24">
            <div className="mx-auto flex max-w-[1240px] flex-col gap-10">
                <FadeIn>
                    <Heading title={t('home.looks')} />
                </FadeIn>
                <FadeIn className="flex items-center gap-8">
                    {slides.length > 1 && (
                        <button type="button" onClick={() => go(index - 1)} aria-label={t('home.previous')} className={arrow}>
                            <ChevronLeft className="size-5 rtl:rotate-180" strokeWidth={1.4} />
                        </button>
                    )}
                    <div className={cn('grid flex-1 items-center gap-8 transition-opacity duration-200 md:grid-cols-[1.25fr_1fr] lg:gap-14', fade ? 'opacity-100' : 'opacity-0')}>
                        <div className="relative aspect-square overflow-hidden bg-mist">
                            <img src={slide.image} alt={t('home.look', { n: index + 1 })} loading="lazy" className="h-full w-full object-cover" />
                            {slide.hotspots.map((h, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    onClick={() => setDot(i)}
                                    aria-label={t('home.show_product', { name: h.product.name })}
                                    aria-pressed={i === dot}
                                    style={{ left: `${h.x}%`, top: `${h.y}%` }}
                                    className={cn(
                                        'absolute size-7 -translate-x-1/2 -translate-y-1/2 rounded-full transition-colors motion-safe:animate-[hotspot_2.2s_ease-in-out_infinite]',
                                        i === dot ? 'bg-ink shadow-[0_0_0_8px_rgba(26,26,26,0.3)]' : 'bg-white shadow-[0_0_0_8px_rgba(255,255,255,0.4)]',
                                    )}
                                />
                            ))}
                        </div>

                        <div className="flex flex-col items-center gap-3 text-center">
                            <Link href={product.url} className="block w-[min(280px,70vw)]">
                                <span className="block aspect-square bg-[#e8e6e3]">
                                    <ProductImage src={product.image} alt={product.name} swatch={product.swatch} sizes="280px" />
                                </span>
                            </Link>
                            <Link href={product.url} className="store-caps mt-2 text-[13px]">
                                {product.name}
                            </Link>
                            <span className="text-[13px] text-subtle">{product.price_varies ? t('product.from', { price: money(product.price, locale) }) : money(product.price, locale)}</span>
                            {product.in_stock &&
                                (product.quick_variant_id ? (
                                    <AddToCartButton variantId={product.quick_variant_id} label={t('pdp.add')} className="store-caps mt-2 inline-flex h-12 items-center justify-center bg-ink px-8 text-[13px] text-white disabled:opacity-70" />
                                ) : (
                                    // Has sizes or colours: the quick-add window asks first.
                                    <button type="button" onClick={() => cart.openQuick(product.url)} aria-haspopup="dialog" className="store-caps mt-2 inline-flex h-12 items-center bg-ink px-8 text-[13px] text-white">
                                        {t('pdp.add')}
                                    </button>
                                ))}
                            {slides.length > 1 && (
                                <div className="mt-4 flex gap-2">
                                    {slides.map((_, i) => (
                                        <button key={i} type="button" onClick={() => go(i)} aria-label={t('home.look', { n: i + 1 })} aria-current={i === index} className="flex size-5 items-center justify-center">
                                            <span className={cn('size-1.5 rounded-full', i === index ? 'bg-ink' : 'bg-[#c4c4c4]')} />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                    {slides.length > 1 && (
                        <button type="button" onClick={() => go(index + 1)} aria-label={t('home.next')} className={arrow}>
                            <ChevronRight className="size-5 rtl:rotate-180" strokeWidth={1.4} />
                        </button>
                    )}
                </FadeIn>
            </div>
        </section>
    );
}

/* ---------------- Sale banner with countdown ---------------- */

export function SaleBanner({ data }: { data: { image: string | null; kicker: string; title: string; text: string; button: string; url: string; endsAt: string | null } }) {
    const t = useT();
    const [left, setLeft] = useState<number | null>(null);

    // Client only (the server time and the visitor's clock can differ).
    useEffect(() => {
        if (!data.endsAt) return;
        const end = new Date(data.endsAt).getTime();
        const tick = () => setLeft(Math.max(0, Math.floor((end - Date.now()) / 1000)));
        tick();
        const timer = window.setInterval(tick, 1000);

        return () => window.clearInterval(timer);
    }, [data.endsAt]);

    if (left === 0) return null;

    const parts = left === null ? null : [Math.floor(left / 86400), Math.floor((left % 86400) / 3600), Math.floor((left % 3600) / 60), left % 60];
    const labels = [t('home.days'), t('home.hours'), t('home.minutes'), t('home.seconds')];

    return (
        <FadeIn as="section" className="relative overflow-hidden text-white">
            {data.image && <img src={data.image} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />}
            <div className="absolute inset-0 bg-gradient-to-r from-black/60 to-black/10 rtl:bg-gradient-to-l" />
            <div className="relative mx-auto flex min-h-[460px] max-w-[1360px] flex-col justify-center gap-10 px-6 py-16 sm:px-10 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex max-w-[560px] flex-col items-start gap-4">
                    {data.kicker && <p className="store-caps text-xs">{data.kicker}</p>}
                    <h2 className="text-[clamp(26px,3.4vw,40px)] font-light uppercase ltr:tracking-[0.14em]">{data.title}</h2>
                    {data.text && <p className="text-[15px] leading-relaxed">{data.text}</p>}
                    {data.button && (
                        <Link href={data.url} className="store-caps mt-2 inline-flex h-12 items-center bg-white px-8 text-[13px] text-ink transition-colors hover:bg-ink hover:text-white">
                            {data.button}
                        </Link>
                    )}
                </div>
                {parts && (
                    <div className="flex items-start gap-3 sm:gap-4" role="timer" dir="ltr">
                        {parts.map((value, i) => (
                            <div key={i} className="flex items-start gap-3 sm:gap-4">
                                <div className="flex min-w-14 flex-col items-center sm:min-w-18">
                                    <span className="text-[clamp(32px,4vw,50px)] leading-none font-light tabular-nums">{String(value).padStart(2, '0')}</span>
                                    <span className="store-caps mt-2 text-[11px]">{labels[i]}</span>
                                </div>
                                {i < 3 && <span className="text-[clamp(24px,3vw,36px)] leading-none font-light">:</span>}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </FadeIn>
    );
}

/* ---------------- Choose your colour (before / after) ---------------- */

type Side = { image: string | null; label: string; url: string };

export function ColourCompare({ data }: { data: { title: string; left: Side; right: Side } }) {
    const t = useT();
    const box = useRef<HTMLDivElement>(null);
    const [pos, setPos] = useState(50);
    const dragging = useRef(false);

    const move = (clientX: number) => {
        const r = box.current?.getBoundingClientRect();
        if (r) setPos(Math.min(100, Math.max(0, ((clientX - r.left) / r.width) * 100)));
    };

    return (
        <section className="px-4 py-20 sm:px-10 lg:py-24">
            <div className="mx-auto flex max-w-[1240px] flex-col gap-10">
                <FadeIn>
                    <Heading title={data.title} />
                </FadeIn>
                <FadeIn>
                    <div
                        ref={box}
                        dir="ltr"
                        className="relative aspect-[4/5] cursor-ew-resize touch-none overflow-hidden bg-[#ddd] select-none sm:aspect-[16/9]"
                        onPointerDown={(e) => {
                            dragging.current = true;
                            e.currentTarget.setPointerCapture(e.pointerId);
                            move(e.clientX);
                        }}
                        onPointerMove={(e) => dragging.current && move(e.clientX)}
                        onPointerUp={() => (dragging.current = false)}
                    >
                        {data.left.image && <img src={data.left.image} alt={data.left.label} draggable={false} className="absolute inset-0 h-full w-full object-cover" />}
                        {data.right.image && <img src={data.right.image} alt={data.right.label} draggable={false} style={{ clipPath: `inset(0 0 0 ${pos}%)` }} className="absolute inset-0 h-full w-full object-cover" />}
                        <div className="pointer-events-none absolute inset-y-0 w-0.5 bg-white" style={{ left: `${pos}%` }} />
                        <input
                            type="range"
                            min={0}
                            max={100}
                            value={Math.round(pos)}
                            onChange={(e) => setPos(Number(e.target.value))}
                            aria-label={t('home.drag')}
                            className="absolute inset-0 z-10 h-full w-full cursor-ew-resize opacity-0"
                        />
                        <span className="pointer-events-none absolute top-1/2 flex size-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white text-ink shadow-md" style={{ left: `${pos}%` }} aria-hidden="true">
                            <ChevronLeft className="size-4" strokeWidth={1.5} />
                            <ChevronRight className="-ms-1 size-4" strokeWidth={1.5} />
                        </span>
                        <div className="absolute bottom-6 left-6 z-20 flex flex-col items-start gap-2 bg-white/85 px-4 py-3 text-ink backdrop-blur-sm">
                            <span className="store-caps text-[13px]">{data.left.label}</span>
                            <Link href={data.left.url} className="border-b border-ink text-[13px]">
                                {t('home.view_product')}
                            </Link>
                        </div>
                        <div className="absolute right-6 bottom-6 z-20 flex flex-col items-end gap-2 bg-white/85 px-4 py-3 text-ink backdrop-blur-sm">
                            <span className="store-caps text-[13px]">{data.right.label}</span>
                            <Link href={data.right.url} className="border-b border-ink text-[13px]">
                                {t('home.view_product')}
                            </Link>
                        </div>
                    </div>
                </FadeIn>
            </div>
        </section>
    );
}
