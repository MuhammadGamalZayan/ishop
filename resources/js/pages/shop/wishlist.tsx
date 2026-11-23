import { Head, Link } from '@inertiajs/react';
import { ProductCard } from '@/components/shop/product-card';
import { useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

/** Saved products. Works for guests too; it moves into the account at sign-in. */
export default function Wishlist({ products }: { products: ProductCardData[] }) {
    const t = useT();
    const { locale } = useShop();

    return (
        <section className="px-4 pt-12 pb-24 sm:px-10">
            <Head title={t('account.wishlist')} />
            <h1 className="mb-10 text-center text-[clamp(24px,3vw,30px)] font-light uppercase ltr:tracking-[0.16em]">{t('account.wishlist')}</h1>
            {products.length === 0 ? (
                <div className="flex flex-col items-center gap-5 py-10 text-center">
                    <p className="max-w-md text-[15px] text-subtle">{t('account.wishlist_empty')}</p>
                    <Link href={`/${locale}`} className="store-caps bg-ink px-6 py-3 text-[13px] text-white">
                        {t('account.start_shopping')}
                    </Link>
                </div>
            ) : (
                <div className="mx-auto grid max-w-[1280px] grid-cols-[repeat(auto-fill,minmax(min(230px,42vw),1fr))] gap-x-7 gap-y-10">
                    {products.map((p) => (
                        <ProductCard key={p.id} product={p} />
                    ))}
                </div>
            )}
        </section>
    );
}
