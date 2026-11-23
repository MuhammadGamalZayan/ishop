import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Heart } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useCart } from '@/components/shop/cart-context';
import { ProductCard } from '@/components/shop/product-card';
import { ProductImage } from '@/components/shop/product-image';
import { money, useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

type Value = { id: number; name: string; hex: string | null };
type Attribute = { id: number; name: string; type: 'color' | 'select'; values: Value[] };
type Variant = { id: number; sku: string; price: number; compare_at: number | null; values: number[]; in_stock: boolean; left: number | null };
type Image = { large: string; thumb: string };
type Product = {
    id: number;
    name: string;
    short_description: string | null;
    description: string | null;
    brand: string | null;
    meta_title: string;
    meta_description: string | null;
    breadcrumb: { name: string; url: string } | null;
    categories: { name: string; url: string }[];
    badge: 'best' | null;
    images: Image[];
    colour_images: Record<number, Image[]>;
    attributes: Attribute[];
    variants: Variant[];
    default_variant_id: number;
    related: ProductCardData[];
};

/** Product page from the mockups. Colour as swatches, other options as dropdowns; sold-out combinations disabled. */
export default function ProductPage({ product }: { product: Product }) {
    const t = useT();
    const { locale, wishlist } = useShop();
    const cart = useCart();

    const defaultVariant = product.variants.find((v) => v.id === product.default_variant_id) ?? product.variants[0];
    const [selected, setSelected] = useState<Record<number, number | null>>(() =>
        Object.fromEntries(product.attributes.map((a) => [a.id, defaultVariant.values.find((id) => a.values.some((v) => v.id === id)) ?? null])),
    );
    const [imageIndex, setImageIndex] = useState(0);
    const [open, setOpen] = useState<Record<string, boolean>>({});

    const variant = useMemo(
        () => (product.attributes.length === 0 ? defaultVariant : product.variants.find((v) => product.attributes.every((a) => selected[a.id] !== null && v.values.includes(selected[a.id]!)))),
        [selected, product, defaultVariant],
    );

    /** Is there an in-stock variant with this value, given the other choices? */
    const available = (attributeId: number, valueId: number) =>
        product.variants.some(
            (v) => v.in_stock && v.values.includes(valueId) && product.attributes.every((a) => a.id === attributeId || selected[a.id] === null || v.values.includes(selected[a.id]!)),
        );

    const colourAttr = product.attributes.find((a) => a.type === 'color');
    const colourId = colourAttr ? selected[colourAttr.id] : null;
    const images = [...((colourId && product.colour_images[colourId]) || []), ...product.images];
    const current = images[Math.min(imageIndex, images.length - 1)];
    const swatch = colourAttr?.values.find((v) => v.id === colourId)?.hex ?? null;

    const missing = product.attributes.find((a) => selected[a.id] === null);
    const ready = !!variant && variant.in_stock;
    const shown = variant ?? defaultVariant;
    const saved = wishlist.includes(product.id);

    const pick = (attributeId: number, valueId: number | null) => {
        setSelected({ ...selected, [attributeId]: valueId });
        setImageIndex(0);
    };

    const status = !variant
        ? { text: missing ? t('pdp.pick_first', { option: missing.name }) : t('pdp.unavailable'), colour: missing ? '#9a9a9a' : '#b3261e' }
        : !variant.in_stock
          ? { text: t('pdp.unavailable'), colour: '#b3261e' }
          : variant.left !== null
            ? { text: t('pdp.left', { count: variant.left }), colour: '#b26a00' }
            : { text: t('pdp.in_stock'), colour: '#2e7d32' };

    return (
        <>
            <Head title={product.meta_title}>{product.meta_description && <meta name="description" content={product.meta_description} />}</Head>

            <section className="mx-auto flex max-w-[1360px] flex-wrap gap-14 px-4 pt-8 pb-20 sm:px-10">
                <div className="flex min-w-0 flex-[999_1_560px] gap-5">
                    {images.length > 1 && (
                        <div role="group" aria-label={t('pdp.gallery')} className="hidden flex-none flex-col gap-3 sm:flex">
                            {images.map((image, i) => (
                                <button
                                    key={image.thumb + i}
                                    type="button"
                                    onClick={() => setImageIndex(i)}
                                    aria-pressed={i === imageIndex}
                                    aria-label={t('pdp.image', { n: i + 1 })}
                                    className={`size-18 overflow-hidden border bg-[#e6e6e6] ${i === imageIndex ? 'border-ink' : 'border-transparent'}`}
                                >
                                    <img src={image.thumb} alt="" className="h-full w-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="flex aspect-square min-w-0 flex-1 items-center justify-center lg:aspect-auto lg:min-h-[620px]">
                        <ProductImage src={current?.large ?? null} alt={product.name} swatch={swatch} sizes="(min-width: 1024px) 50vw, 100vw" />
                    </div>
                </div>

                <div className="flex max-w-[480px] min-w-0 flex-[1_1_380px] flex-col gap-5.5">
                    <nav aria-label={t('category.breadcrumb')} className="store-caps text-xs text-[#4a4a4a]">
                        <Link href={`/${locale}`}>{t('category.home')}</Link>
                        {product.breadcrumb && (
                            <>
                                <span aria-hidden="true"> / </span>
                                <Link href={product.breadcrumb.url}>{product.breadcrumb.name}</Link>
                            </>
                        )}
                    </nav>
                    {product.badge && <p className="store-caps text-xs text-[#4a4a4a]">{t(`product.badges.${product.badge}`)}</p>}
                    <h1 className="store-caps text-[28px] leading-snug font-normal">{product.name}</h1>
                    <div className="flex flex-col gap-1 border-b border-line pb-5.5">
                        <span className="text-xl text-[#3a3a3a]">
                            {shown.compare_at && <s className="me-3 text-base text-subtle">{money(shown.compare_at, locale)}</s>}
                            {money(shown.price, locale)}
                        </span>
                        <span className="text-xs text-subtle">{t('pdp.vat')}</span>
                    </div>

                    {product.attributes.map((attribute) => {
                        const value = selected[attribute.id];
                        const valueName = attribute.values.find((v) => v.id === value)?.name;

                        return attribute.type === 'color' ? (
                            <fieldset key={attribute.id} className="flex flex-col gap-3">
                                <legend className="mb-3 text-sm">
                                    <span className="text-subtle">{attribute.name}:</span> {valueName}
                                </legend>
                                <div className="flex flex-wrap gap-2.5">
                                    {attribute.values.map((v) => {
                                        const on = v.id === value;
                                        const ok = available(attribute.id, v.id);

                                        return (
                                            <button
                                                key={v.id}
                                                type="button"
                                                onClick={() => pick(attribute.id, v.id)}
                                                aria-pressed={on}
                                                aria-label={ok ? v.name : t('pdp.sold_out_option', { name: v.name })}
                                                title={ok ? v.name : t('pdp.sold_out_option', { name: v.name })}
                                                className={`relative size-11 border p-[3px] ${on ? 'border-ink' : 'border-transparent'}`}
                                            >
                                                <span className={`block h-full w-full outline outline-1 outline-black/10 ${ok ? '' : 'opacity-40'}`} style={{ background: v.hex ?? '#ccc' }} />
                                                {!ok && <span aria-hidden="true" className="absolute inset-0 m-auto h-px w-[130%] -translate-x-[12%] rotate-45 bg-ink" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        ) : (
                            <label key={attribute.id} className="flex flex-col gap-2 text-sm">
                                <span className="text-subtle">{attribute.name}</span>
                                <select
                                    value={value ?? ''}
                                    onChange={(e) => pick(attribute.id, e.target.value ? Number(e.target.value) : null)}
                                    className="h-12.5 border border-[#c9c9c9] bg-white px-3.5 text-[15px]"
                                >
                                    <option value="">{t('pdp.choose')}</option>
                                    {attribute.values.map((v) => {
                                        const ok = available(attribute.id, v.id);

                                        return (
                                            <option key={v.id} value={v.id} disabled={!ok}>
                                                {ok ? v.name : t('pdp.sold_out_option', { name: v.name })}
                                            </option>
                                        );
                                    })}
                                </select>
                            </label>
                        );
                    })}

                    <p role="status" className="flex items-center gap-2 text-[13px] text-[#3a3a3a]">
                        <span aria-hidden="true" className="size-2 rounded-full" style={{ background: status.colour }} />
                        {status.text}
                    </p>

                    <div className="flex gap-2.5">
                        <button
                            type="button"
                            disabled={!ready || cart.busy}
                            onClick={() => variant && cart.add(variant.id)}
                            className="store-caps h-13.5 flex-1 border border-ink text-sm transition enabled:hover:bg-ink enabled:hover:text-white disabled:cursor-not-allowed disabled:border-[#9a9a9a] disabled:text-[#6b6b6b]"
                        >
                            {ready ? t('pdp.add') : missing ? t('pdp.pick_first', { option: missing.name }) : t('product.sold_out')}
                        </button>
                        <button
                            type="button"
                            onClick={() => router.post(`/wishlist/${product.id}`, {}, { preserveScroll: true, preserveState: true })}
                            aria-pressed={saved}
                            aria-label={saved ? t('pdp.wishlist_remove') : t('pdp.wishlist_add')}
                            className="flex size-13.5 flex-none items-center justify-center border border-ink"
                        >
                            <Heart className="size-5" strokeWidth={1.3} fill={saved ? 'currentColor' : 'none'} />
                        </button>
                    </div>

                    {product.short_description && <p className="text-[15px] leading-relaxed text-[#3a3a3a]">{product.short_description}</p>}

                    {product.categories.length > 0 && (
                        <div className="flex flex-wrap gap-2.5">
                            {product.categories.map((c) => (
                                <Link key={c.url} href={c.url} className="border border-[#c9c9c9] px-3 py-2 text-[13px] text-[#3a3a3a]">
                                    {c.name}
                                </Link>
                            ))}
                        </div>
                    )}

                    <div className="border-t border-line">
                        {[
                            { key: 'details', title: t('pdp.details'), body: product.description, extra: [product.brand && `${t('pdp.brand')}: ${product.brand}`, variant && `${t('pdp.sku')}: ${variant.sku}`].filter(Boolean).join(' · ') },
                            { key: 'shipping', title: t('pdp.shipping'), body: t('pdp.shipping_text'), extra: '' },
                        ].map((section) => (
                            <div key={section.key} className="border-b border-line">
                                <button type="button" onClick={() => setOpen({ ...open, [section.key]: !open[section.key] })} aria-expanded={!!open[section.key]} className="store-caps flex h-14 w-full items-center justify-between text-[13px]">
                                    {section.title}
                                    <ChevronDown className={`size-3 transition-transform ${open[section.key] ? 'rotate-180' : ''}`} aria-hidden="true" />
                                </button>
                                {open[section.key] && (
                                    <div className="flex flex-col gap-2 pb-5 text-sm leading-relaxed whitespace-pre-line text-[#3a3a3a]">
                                        {section.body && <p>{section.body}</p>}
                                        {section.extra && <p className="text-xs text-subtle">{section.extra}</p>}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {product.related.length > 0 && (
                <section className="px-4 pt-22 pb-24 sm:px-10">
                    <h2 className="mb-12 text-center text-[26px] font-light uppercase ltr:tracking-[0.2em]">{t('pdp.also_like')}</h2>
                    <div className="mx-auto grid max-w-[1280px] grid-cols-[repeat(auto-fill,minmax(min(230px,42vw),1fr))] gap-x-7 gap-y-10">
                        {product.related.map((p) => (
                            <ProductCard key={p.id} product={p} />
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
