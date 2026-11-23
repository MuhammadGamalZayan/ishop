import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCart } from '@/components/shop/cart-context';
import { ProductImage } from '@/components/shop/product-image';
import { money, useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

/**
 * Card from the mockups: badge, image (second photo on hover), "+" quick add,
 * name in spaced capitals, price. A product with nothing to choose is added
 * straight away; one with options opens its page to pick colour/size.
 */
export function ProductCard({ product }: { product: ProductCardData }) {
    const { locale } = useShop();
    const t = useT();
    const cart = useCart();
    const badge = product.badge ? t(product.badge === 'sold_out' ? 'product.sold_out' : `product.badges.${product.badge}`) : null;
    const price = product.price_varies ? t('product.from', { price: money(product.price, locale) }) : money(product.price, locale);

    return (
        <article className="group flex flex-col gap-3.5">
            <div className="relative aspect-square">
                {badge && <span className="store-caps absolute start-0 top-0 z-10 text-[11px] text-[#4a4a4a]">{badge}</span>}
                <Link href={product.url} aria-label={product.name} className="block h-full">
                    <ProductImage src={product.image} hoverSrc={product.image_hover} alt={product.name} swatch={product.swatch} />
                </Link>
                {product.in_stock &&
                    (product.quick_variant_id ? (
                        <button
                            type="button"
                            onClick={() => cart.add(product.quick_variant_id!)}
                            disabled={cart.busy}
                            aria-label={`${t('product.quick_add')}: ${product.name}`}
                            className="absolute end-0 bottom-0 flex size-11 items-center justify-center bg-white transition hover:bg-ink hover:text-white disabled:opacity-60"
                        >
                            <Plus className="size-4" strokeWidth={1.4} />
                        </button>
                    ) : (
                        <Link
                            href={product.url}
                            aria-label={`${t('product.choose_options')}: ${product.name}`}
                            className="absolute end-0 bottom-0 flex size-11 items-center justify-center bg-white transition hover:bg-ink hover:text-white"
                        >
                            <Plus className="size-4" strokeWidth={1.4} />
                        </Link>
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
