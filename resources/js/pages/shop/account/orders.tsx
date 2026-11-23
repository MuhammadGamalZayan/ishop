import { Head, Link } from '@inertiajs/react';
import { AccountShell } from '@/components/shop/account-shell';
import { ProductImage } from '@/components/shop/product-image';
import { money, useShop, useT } from '@/lib/shop';

type Order = { number: string; url: string; status: string; status_label: string; placed_at: string; items: string; total: number; images: (string | null)[] };

export const BADGE: Record<string, string> = {
    pending: 'bg-[#fff4e0] text-[#8a4b00]',
    confirmed: 'bg-[#e7eefc] text-[#1d3f8a]',
    processing: 'bg-[#e7eefc] text-[#1d3f8a]',
    shipped: 'bg-[#e7eefc] text-[#1d3f8a]',
    delivered: 'bg-[#e6f4ea] text-[#1e6b34]',
    cancelled: 'bg-[#eeeeee] text-[#4a4a4a]',
    returned: 'bg-[#eeeeee] text-[#4a4a4a]',
};

export default function Orders({ orders, pagination }: { orders: Order[]; pagination: { prev: string | null; next: string | null } }) {
    const t = useT();
    const { locale } = useShop();

    return (
        <AccountShell>
            <Head title={t('account.orders')} />
            <h1 className="text-[26px] font-light uppercase ltr:tracking-[0.16em]">{t('account.orders')}</h1>

            {orders.length === 0 ? (
                <div className="flex flex-col items-start gap-4 bg-white p-8">
                    <p className="text-[15px] text-subtle">{t('account.no_orders')}</p>
                    <Link href={`/${locale}`} className="store-caps bg-ink px-6 py-3 text-[13px] text-white">
                        {t('account.start_shopping')}
                    </Link>
                </div>
            ) : (
                orders.map((order) => (
                    <article key={order.number} className="flex flex-wrap items-center gap-4.5 bg-white px-6 py-5.5">
                        <div className="flex flex-[1_1_240px] flex-col gap-2">
                            <div className="flex flex-wrap items-center gap-3">
                                <span className="font-mono text-[15px]" dir="ltr">{order.number}</span>
                                <span className={`px-2.5 py-0.5 text-xs ${BADGE[order.status] ?? ''}`}>{order.status_label}</span>
                            </div>
                            <span className="text-[13px] text-subtle">
                                {order.placed_at} · {order.items}
                            </span>
                        </div>
                        <div className="flex gap-1.5">
                            {order.images.map((src, i) => (
                                <span key={i} className="flex size-13 items-center justify-center bg-mist">
                                    <ProductImage compact src={src} alt="" />
                                </span>
                            ))}
                        </div>
                        <div className="flex min-w-[150px] flex-col items-end gap-2.5">
                            <span className="text-base">{money(order.total, locale)}</span>
                            <Link href={order.url} className="store-caps border border-ink px-4 py-2.5 text-[13px]">
                                {t('account.view')}
                            </Link>
                        </div>
                    </article>
                ))
            )}

            {(pagination.prev || pagination.next) && (
                <nav className="flex justify-center gap-6 text-sm">
                    {pagination.prev && <Link href={pagination.prev} className="underline underline-offset-4">{t('category.previous')}</Link>}
                    {pagination.next && <Link href={pagination.next} className="underline underline-offset-4">{t('category.next')}</Link>}
                </nav>
            )}
        </AccountShell>
    );
}
