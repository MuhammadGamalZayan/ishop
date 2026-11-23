import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Heart } from 'lucide-react';
import { useState } from 'react';
import { AddToCartButton } from '@/components/shop/add-to-cart-button';
import { ProductCard } from '@/components/shop/product-card';
import { ProductImage } from '@/components/shop/product-image';
import { VariantOptions, useVariantPicker } from '@/components/shop/variant-picker';
import type { OptionAttribute, PickVariant } from '@/components/shop/variant-picker';
import { money, useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';


type Attribute = OptionAttribute;
type Variant = PickVariant;
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

    const picker = useVariantPicker(product.attributes, product.variants, product.default_variant_id);
    const { selected, variant, missing, ready, shown } = picker;
    const [imageIndex, setImageIndex] = useState(0);
    const [open, setOpen] = useState<Record<string, boolean>>({});

    const colourAttr = product.attributes.find((a) => a.type === 'color');
    const colourId = colourAttr ? selected[colourAttr.id] : null;
    const images = [...((colourId && product.colour_images[colourId]) || []), ...product.images];
    const current = images[Math.min(imageIndex, images.length - 1)];
    const swatch = colourAttr?.values.find((v) => v.id === colourId)?.hex ?? null;
    const saved = wishlist.includes(product.id);

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

                    <VariantOptions attributes={product.attributes} picker={picker} onPick={() => setImageIndex(0)} />

                    <div className="flex gap-2.5">
                        <AddToCartButton
                            variantId={variant?.id ?? null}
                            ready={ready}
                            label={ready ? t("pdp.add") : missing ? t("pdp.pick_first", { option: missing.name }) : t("product.sold_out")}
                            className="store-caps h-13.5 flex-1 border border-ink text-sm transition enabled:hover:bg-ink enabled:hover:text-white disabled:cursor-not-allowed disabled:border-[#9a9a9a] disabled:text-[#6b6b6b]"
                        />
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
