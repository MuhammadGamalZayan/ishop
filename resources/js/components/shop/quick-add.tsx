import * as Dialog from '@radix-ui/react-dialog';
import { Link } from '@inertiajs/react';
import { Loader2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AddToCartButton } from '@/components/shop/add-to-cart-button';
import { useCart } from '@/components/shop/cart-context';
import { ProductImage } from '@/components/shop/product-image';
import { VariantOptions, useVariantPicker } from '@/components/shop/variant-picker';
import type { OptionAttribute, PickVariant } from '@/components/shop/variant-picker';
import { money, useShop, useT } from '@/lib/shop';

type QuickProduct = {
    name: string;
    url: string;
    images: { large: string; thumb: string }[];
    colour_images: Record<number, { large: string; thumb: string }[]>;
    attributes: OptionAttribute[];
    variants: PickVariant[];
    default_variant_id: number;
};

/**
 * Quick-add window (approved mockup): the "+" on a product with options
 * opens this instead of leaving the page. Pick colour / size, add, and the
 * cart drawer opens.
 */
export function QuickAdd() {
    const cart = useCart();
    const { dir } = useShop();
    const t = useT();
    const [product, setProduct] = useState<QuickProduct | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!cart.quickUrl) {
            setProduct(null);
            setFailed(false);

            return;
        }

        const controller = new AbortController();
        fetch(cart.quickUrl, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
            .then(setProduct)
            .catch((e) => e?.name !== 'AbortError' && setFailed(true));

        return () => controller.abort();
    }, [cart.quickUrl]);

    return (
        <Dialog.Root open={cart.quickUrl !== null} onOpenChange={(open) => !open && cart.closeQuick()}>
            <Dialog.Portal>
                <Dialog.Overlay className="data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/45" />
                <Dialog.Content
                    dir={dir}
                    aria-describedby={undefined}
                    className="store data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100vh-32px)] w-[780px] max-w-[calc(100vw-32px)] -translate-x-1/2 -translate-y-1/2 flex-wrap overflow-y-auto bg-white"
                >
                    <Dialog.Close aria-label={t('nav.close')} className="absolute end-2.5 top-2.5 z-10 flex size-11 items-center justify-center">
                        <X className="size-[18px]" strokeWidth={1.3} />
                    </Dialog.Close>

                    {!product && (
                        <div className="flex min-h-[340px] w-full items-center justify-center p-10" role="status">
                            {failed ? (
                                <p className="text-sm text-subtle">{t('cart.quick_failed')}</p>
                            ) : (
                                <Loader2 className="size-6 animate-spin text-subtle" aria-label={t('product.quick_add')} />
                            )}
                            <Dialog.Title className="sr-only">{t('product.quick_add')}</Dialog.Title>
                        </div>
                    )}

                    {product && <QuickAddBody product={product} />}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function QuickAddBody({ product }: { product: QuickProduct }) {
    const t = useT();
    const { locale } = useShop();
    const cart = useCart();
    const picker = useVariantPicker(product.attributes, product.variants, product.default_variant_id);
    const colourAttr = product.attributes.find((a) => a.type === 'color');
    const colourId = colourAttr ? picker.selected[colourAttr.id] : null;
    const image = ((colourId && product.colour_images[colourId]) || product.images)[0];
    const swatch = colourAttr?.values.find((v) => v.id === colourId)?.hex ?? null;
    const { ready, missing, variant, shown } = picker;

    return (
        <>
            <div className="flex min-h-[300px] flex-[1_1_320px] items-center justify-center bg-mist">
                <ProductImage src={image?.large ?? null} alt={product.name} swatch={swatch} />
            </div>
            <div className="flex flex-[1_1_320px] flex-col gap-5.5 px-9 pt-11 pb-9">
                <div className="flex flex-col gap-2.5">
                    <Dialog.Title className="store-caps text-xl leading-snug font-normal">{product.name}</Dialog.Title>
                    <p className="text-base text-subtle">
                        {shown.compare_at && <s className="me-2">{money(shown.compare_at, locale)}</s>}
                        {money(shown.price, locale)}
                    </p>
                </div>
                <VariantOptions attributes={product.attributes} picker={picker} />
                <AddToCartButton
                    variantId={variant?.id ?? null}
                    ready={ready}
                    label={ready ? t('pdp.add') : missing ? t('pdp.pick_first', { option: missing.name }) : t('product.sold_out')}
                    className="store-caps h-13 bg-ink text-sm text-white disabled:cursor-not-allowed disabled:bg-[#8a8a8a]"
                />
                <Link href={product.url} onClick={cart.closeQuick} className="self-start text-[13px] underline underline-offset-4">
                    {t('cart.full_details')}
                </Link>
            </div>
        </>
    );
}
