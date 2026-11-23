import { Link, router } from '@inertiajs/react';
import { Heart, Loader2, Plus } from 'lucide-react';
import { useState } from 'react';
import { AddToCartButton } from '@/components/shop/add-to-cart-button';
import { useCart } from '@/components/shop/cart-context';
import { ProductImage } from '@/components/shop/product-image';
import { money, useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

const PLUS = 'absolute end-0 bottom-0 flex size-11 items-center justify-center bg-white transition hover:bg-ink hover:text-white disabled:opacity-70';

/**
 * Card from the mockups: badge, image (second photo on hover), "+" quick add,
 * name in spaced capitals, price. "+" adds a product with nothing to choose
 * straight away (spinner until it is in the cart); for one with options it
 * opens the quick-add window to pick colour / size without leaving the page.
 */
export function ProductCard({ product }: { product: ProductCardData }) {
    const { locale, wishlist } = useShop();
    const [saving, setSaving] = useState(false);
    const saved = wishlist.includes(product.id);
    const t = useT();
    const cart = useCart();
    const badge = product.badge ? t(product.badge === 'sold_out' ? 'product.sold_out' : `product.badges.${product.badge}`) : null;
    const price = product.price_varies ? t('product.from', { price: money(product.price, locale) }) : money(product.price, locale);
    const adding = product.quick_variant_id !== null && cart.adding === product.quick_variant_id;

    // Full reload of the page props (no `only`), so on the wishlist page the
    // card simply drops out of the list once it is removed.
    const toggleSaved = () => {
        setSaving(true);
        router.post(`/wishlist/${product.id}`, {}, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <article className="group flex flex-col gap-3.5">
            <div className="relative aspect-square">
                {badge && <span className="store-caps absolute start-0 top-0 z-10 text-[11px] text-[#4a4a4a]">{badge}</span>}
                <Link href={product.url} aria-label={product.name} className="block h-full">
                    <ProductImage src={product.image} hoverSrc={product.image_hover} alt={product.name} swatch={product.swatch} />
                </Link>
                <button
                    type="button"
                    onClick={toggleSaved}
                    disabled={saving}
                    aria-pressed={saved}
                    aria-label={`${saved ? t('pdp.wishlist_remove') : t('pdp.wishlist_add')}: ${product.name}`}
                    className="absolute end-0 top-0 z-10 flex size-10 items-center justify-center text-ink transition hover:scale-110 disabled:opacity-60"
                >
                    {saving ? (
                        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                    ) : (
                        <Heart className="size-4.5" strokeWidth={1.4} fill={saved ? 'currentColor' : 'none'} aria-hidden="true" />
                    )}
                </button>
                {product.in_stock &&
                    (product.quick_variant_id ? (
                        <AddToCartButton
                            variantId={product.quick_variant_id}
                            ariaLabel={`${t('product.quick_add')}: ${product.name}`}
                            label={!adding && <Plus className="size-4" strokeWidth={1.4} aria-hidden="true" />}
                            className={`${PLUS} gap-0`}
                        />
                    ) : (
                        <button type="button" onClick={() => cart.openQuick(product.url)} aria-label={`${t('product.quick_add')}: ${product.name}`} aria-haspopup="dialog" className={PLUS}>
                            <Plus className="size-4" strokeWidth={1.4} aria-hidden="true" />
                        </button>
                    ))}
            </div>
            <Link href={product.url} className="flex flex-col gap-1.5 text-center">
                <span className="store-caps text-[13px] leading-relaxed">{product.name}</span>
                <span className="text-[13px] text-subtle">
                    {product.compare_at && <s className="me-2">{money(product.compare_at, locale)}</s>}
                    {price}
                </span>
            </Link>
        </article>
    );
}
