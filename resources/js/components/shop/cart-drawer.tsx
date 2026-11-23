import { Link, router } from '@inertiajs/react';
import { AlertCircle, Loader2, Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import { AddToCartButton } from '@/components/shop/add-to-cart-button';
import { useCart } from '@/components/shop/cart-context';
import { Drawer } from '@/components/shop/drawer';
import { ProductImage } from '@/components/shop/product-image';
import { money, useShop, useT } from '@/lib/shop';
import type { CartLine } from '@/lib/shop';

/**
 * Cart drawer from the mockups. Lines come fresh from the server each time it
 * opens; a line that changed since it was added (removed, colour dropped,
 * sold out, less stock) shows why, and Checkout stays disabled until fixed.
 */
export function CartDrawer() {
    const { cartDrawer, locale } = useShop();
    const t = useT();
    const cart = useCart();
    const [pending, setPending] = useState<number | null>(null);

    const lines = cartDrawer?.lines ?? [];
    const loading = cart.open && !cartDrawer;

    const update = (line: CartLine, quantity: number) => {
        setPending(line.id);
        const options = { preserveScroll: true, preserveState: true, only: ['cartDrawer', 'cart', 'flash'], onFinish: () => setPending(null) };

        if (quantity <= 0) {
            router.delete(`/cart/items/${line.id}`, options);
        } else {
            router.patch(`/cart/items/${line.id}`, { quantity }, options);
        }
    };

    const title = `${t('cart.title')}${cartDrawer?.count ? ` (${cartDrawer.count})` : ''}`;

    const footer =
        lines.length > 0 ? (
            <div className="flex flex-col gap-3.5">
                <p className="text-[13px] text-subtle">{t('cart.shipping_note')}</p>
                {cartDrawer?.has_issues ? (
                    <>
                        <button type="button" disabled aria-describedby="cart-issues" className="store-caps h-13 cursor-not-allowed bg-[#9a9a9a] text-sm text-white">
                            {t('cart.checkout')}
                        </button>
                        <p id="cart-issues" role="alert" className="text-center text-[13px] text-alert">
                            {t('cart.fix_first')}
                        </p>
                    </>
                ) : (
                    <>
                        {/* A plain link: guests go through sign-in and come back here (intended URL). */}
                        <a href={`/${locale}/checkout`} className="store-caps flex h-13 items-center justify-center gap-3 bg-ink text-sm text-white">
                            <span>{t('cart.checkout')}</span>
                            <span aria-hidden="true">·</span>
                            <span>{money(cartDrawer?.subtotal ?? 0, locale)}</span>
                        </a>
                        <p className="text-center text-xs text-subtle">{t('cart.login_note')}</p>
                    </>
                )}
            </div>
        ) : undefined;

    return (
        <Drawer open={cart.open} onOpenChange={(open) => (open ? cart.openDrawer() : cart.closeDrawer())} title={title} footer={footer} className="bg-mist">
            {loading && (
                <div className="flex justify-center p-10" role="status">
                    <Loader2 className="size-6 animate-spin text-subtle" aria-hidden="true" />
                    <span className="sr-only">{t('cart.title')}</span>
                </div>
            )}

            {!loading && lines.length === 0 && (
                <div className="flex h-full flex-col items-center justify-center gap-6 p-7 text-center">
                    <p className="store-caps text-sm">{t('cart.empty')}</p>
                    <button type="button" onClick={cart.closeDrawer} className="store-caps h-11.5 bg-ink px-8 text-[13px] text-white">
                        {t('cart.continue')}
                    </button>
                </div>
            )}

            {lines.length > 0 && (
                <ul className="px-7">
                    {lines.map((line) => (
                        <li key={line.id} className="flex gap-4.5 border-b border-line py-6" aria-busy={pending === line.id}>
                            <div className="flex size-23 flex-none items-center justify-center bg-[#e6e6e6]">
                                <ProductImage compact src={line.image} alt="" swatch={line.swatch} className={line.must_remove ? 'opacity-50' : undefined} />
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                {line.url ? (
                                    <Link href={line.url} onClick={cart.closeDrawer} className="store-caps text-[13px] leading-relaxed">
                                        {line.name}
                                    </Link>
                                ) : (
                                    <span className="store-caps text-[13px] leading-relaxed text-subtle">{line.name}</span>
                                )}
                                <span className="text-[13px] text-subtle">{money(line.unit_price * line.quantity, locale)}</span>
                                {line.options && <span className="store-caps text-xs text-subtle">{line.options}</span>}
                                {line.issue && (
                                    <span role="status" className="inline-flex items-center gap-1.5 text-xs font-medium text-alert">
                                        <AlertCircle className="size-3.5" aria-hidden="true" />
                                        {line.issue}
                                    </span>
                                )}
                                <div className="mt-2 flex items-center gap-4.5">
                                    {!line.must_remove && (
                                        <div className="flex items-center border border-[#c9c9c9]">
                                            <button type="button" onClick={() => update(line, line.quantity - 1)} disabled={pending === line.id} aria-label={t('cart.decrease')} className="flex h-10.5 w-11 items-center justify-center disabled:opacity-50">
                                                <Minus className="size-3" />
                                            </button>
                                            <span aria-live="polite" className="min-w-6 text-center text-sm">{line.quantity}</span>
                                            <button
                                                type="button"
                                                onClick={() => update(line, line.quantity + 1)}
                                                disabled={pending === line.id || line.quantity >= line.max}
                                                aria-label={t('cart.increase')}
                                                className="flex h-10.5 w-11 items-center justify-center disabled:opacity-30"
                                            >
                                                <Plus className="size-3" />
                                            </button>
                                        </div>
                                    )}
                                    <button type="button" onClick={() => update(line, 0)} disabled={pending === line.id} className="text-[13px] underline underline-offset-4">
                                        {t('cart.remove')}
                                    </button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {cartDrawer?.upsell && lines.length > 0 && (
                <section className="flex flex-col gap-4.5 border-b border-line px-7 py-6.5">
                    <p className="store-caps text-xs">{t('cart.complete_with')}</p>
                    <div className="flex items-center gap-4.5">
                        <div className="flex size-18 flex-none items-center justify-center bg-[#e6e6e6]">
                            <ProductImage compact src={cartDrawer.upsell.image} alt="" swatch={cartDrawer.upsell.swatch} />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Link href={cartDrawer.upsell.url} onClick={cart.closeDrawer} className="store-caps text-[13px]">
                                {cartDrawer.upsell.name}
                            </Link>
                            <span className="text-[13px] text-subtle">{money(cartDrawer.upsell.price, locale)}</span>
                            {cartDrawer.upsell.quick_variant_id ? (
                                <AddToCartButton variantId={cartDrawer.upsell.quick_variant_id} label={t('cart.add')} className="self-start text-[13px] underline underline-offset-4 disabled:opacity-60" />
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => {
                                        cart.closeDrawer();
                                        cart.openQuick(cartDrawer.upsell!.url);
                                    }}
                                    aria-haspopup="dialog"
                                    className="self-start text-[13px] underline underline-offset-4"
                                >
                                    {t('product.choose_options')}
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            )}
        </Drawer>
    );
}
