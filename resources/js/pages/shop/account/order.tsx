import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { AccountShell } from '@/components/shop/account-shell';
import { money, useShop, useT } from '@/lib/shop';
import { BADGE } from '@/pages/shop/account/orders';

type Order = {
    number: string;
    status: string;
    status_label: string;
    placed_at: string;
    can_cancel: boolean;
    payment: string;
    timeline: { label: string; done: boolean; date: string | null }[];
    latest_note: string | null;
    items: { name: string; options: string; quantity: number; total: number }[];
    subtotal: number;
    discount: number;
    shipping: number;
    total: number;
    tax: number;
    coupon: string | null;
    note: string | null;
    address: string[];
};

/** Order page with the tracking line from the approved mockup. */
export default function OrderPage({ order }: { order: Order }) {
    const t = useT();
    const { locale } = useShop();
    const done = order.timeline.filter((s) => s.done).length;

    return (
        <AccountShell>
            <Head title={order.number} />
            <Link href={`/${locale}/account/orders`} className="inline-flex items-center gap-2 self-start text-sm">
                <ArrowLeft className="size-3.5 rtl:rotate-180" aria-hidden="true" />
                {t('account.all_orders')}
            </Link>

            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <h1 className="font-mono text-2xl" dir="ltr">{order.number}</h1>
                <span className="flex items-center gap-3 text-[13px] text-subtle">
                    {t('account.placed_on', { date: order.placed_at })}
                    <span className={`px-2.5 py-0.5 text-xs ${BADGE[order.status] ?? ''}`}>{order.status_label}</span>
                </span>
            </div>

            {order.timeline.length > 0 && (
                <section aria-label={t('account.tracking')} className="bg-white px-6 py-7">
                    <ol className="grid grid-cols-5">
                        {order.timeline.map((step, i) => (
                            <li key={step.label} className="relative flex flex-col items-center gap-2.5 text-center">
                                {i > 0 && <span aria-hidden="true" className={`absolute end-1/2 top-[13px] h-0.5 w-full ${step.done ? 'bg-ink' : 'bg-line'}`} />}
                                <span aria-hidden="true" className={`z-10 flex size-7 items-center justify-center rounded-full border-2 ${step.done ? 'border-ink bg-ink' : 'border-[#c9c9c9] bg-white'}`}>
                                    {step.done && <Check className="size-3.5 text-white" strokeWidth={3} />}
                                </span>
                                <span className={`text-[13px] ${step.done ? 'text-ink' : 'text-[#8a8a8a]'} ${i === done - 1 ? 'font-semibold' : ''}`}>{step.label}</span>
                                <span className="text-xs text-subtle">{step.date}</span>
                            </li>
                        ))}
                    </ol>
                    {order.latest_note && <p className="mt-5.5 bg-[#f5f5f5] px-3.5 py-3 text-sm leading-relaxed">{order.latest_note}</p>}
                </section>
            )}

            <div className="flex flex-wrap gap-5">
                <section className="flex flex-[2_1_380px] flex-col gap-4 bg-white px-6 py-5.5">
                    <h2 className="store-caps text-[15px]">{t('account.items_title')}</h2>
                    {order.items.map((item, i) => (
                        <div key={i} className="flex items-center gap-3.5">
                            <span className="flex flex-1 flex-col gap-1">
                                <span className="text-sm">{item.name}</span>
                                <span className="text-xs text-subtle">
                                    {[item.options, `× ${item.quantity}`].filter(Boolean).join(' · ')}
                                </span>
                            </span>
                            <span className="text-sm whitespace-nowrap">{money(item.total, locale)}</span>
                        </div>
                    ))}
                    <dl className="mt-1.5 grid grid-cols-[1fr_auto] gap-2 border-t border-[#e2e2e2] pt-3.5 text-sm">
                        <dt className="text-subtle">{t('cart.subtotal')}</dt>
                        <dd>{money(order.subtotal, locale)}</dd>
                        {order.discount > 0 && (
                            <>
                                <dt className="text-subtle">
                                    {t('checkout.discount')} {order.coupon && <span dir="ltr">({order.coupon})</span>}
                                </dt>
                                <dd>−{money(order.discount, locale)}</dd>
                            </>
                        )}
                        <dt className="text-subtle">{t('checkout.shipping')}</dt>
                        <dd>{order.shipping === 0 ? t('checkout.free') : money(order.shipping, locale)}</dd>
                        <dt className="font-medium">{t('checkout.total')}</dt>
                        <dd className="font-medium">{money(order.total, locale)}</dd>
                        <dt className="text-xs text-subtle">{t('account.vat_included')}</dt>
                        <dd className="text-xs text-subtle">{money(order.tax, locale)}</dd>
                    </dl>
                </section>

                <section className="flex flex-[1_1_240px] flex-col gap-5">
                    <div className="flex flex-col gap-2 bg-white px-6 py-5.5 text-sm leading-relaxed">
                        <h2 className="store-caps mb-1 text-[15px]">{t('account.delivery_to')}</h2>
                        {order.address.map((line, i) => (
                            <span key={i}>{line}</span>
                        ))}
                    </div>
                    <div className="flex flex-col gap-2 bg-white px-6 py-5.5 text-sm">
                        <h2 className="store-caps mb-1 text-[15px]">{t('account.payment')}</h2>
                        <span>{order.payment}</span>
                    </div>
                    {order.note && (
                        <div className="flex flex-col gap-2 bg-white px-6 py-5.5 text-sm">
                            <h2 className="store-caps mb-1 text-[15px]">{t('account.your_note')}</h2>
                            <p className="whitespace-pre-line">{order.note}</p>
                        </div>
                    )}
                    {order.can_cancel && (
                        <div className="flex flex-col gap-3 bg-white px-6 py-5.5 text-sm">
                            <p className="text-subtle">{t('account.cancel_rule')}</p>
                            <button
                                type="button"
                                onClick={() => window.confirm(t('account.cancel_confirm')) && router.post(`/account/orders/${order.number}/cancel`, {}, { preserveScroll: true })}
                                className="self-start border border-alert px-4.5 py-2.5 text-[13px] text-alert"
                            >
                                {t('account.cancel')}
                            </button>
                        </div>
                    )}
                </section>
            </div>
        </AccountShell>
    );
}
