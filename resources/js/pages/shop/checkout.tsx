import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AddressFields, addressComplete, addressPayload, emptyAddress } from '@/components/shop/address-fields';
import type { AddressValues, Governorate } from '@/components/shop/address-fields';
import { useCart } from '@/components/shop/cart-context';
import { ProductImage } from '@/components/shop/product-image';
import { money, useShop, useT } from '@/lib/shop';
import type { CartLine } from '@/lib/shop';

type Quote = {
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    tax_total: number;
    grand_total: number;
    coupon: { code: string; type: string } | null;
    coupon_error: string | null;
    item_count: number;
    has_area: boolean;
};
type SavedAddress = { id: number; label: string | null; is_default: boolean; shipping_area_id: number; text: string };
type Props = {
    idempotencyKey: string;
    email: string;
    lines: CartLine[];
    quote: Quote;
    addresses: SavedAddress[];
    governorates: Governorate[];
    areaId: number | null;
};

/**
 * Checkout (approved mockup): Cart → Information → Payment.
 * If any cart line changed since it was added, the warning lists it and
 * Continue stays disabled; the server refuses the order anyway.
 */
export default function Checkout({ idempotencyKey, email, lines, quote, addresses, governorates, areaId }: Props) {
    const t = useT();
    const { locale } = useShop();
    const cart = useCart();
    const errors = usePage().props.errors as Record<string, string>;

    const [step, setStep] = useState<'info' | 'payment'>('info');
    const [addressId, setAddressId] = useState<number | null>(addresses[0]?.id ?? null);
    const [values, setValues] = useState<AddressValues>(emptyAddress);
    const [save, setSave] = useState(true);
    const [note, setNote] = useState('');
    const [code, setCode] = useState('');
    const [placing, setPlacing] = useState(false);

    // The cart may have been edited in the drawer: refresh lines and totals when it closes.
    const [wasOpen, setWasOpen] = useState(false);
    useEffect(() => {
        if (cart.open) setWasOpen(true);
        else if (wasOpen) {
            setWasOpen(false);
            router.reload({ only: ['lines', 'quote'] });
        }
    }, [cart.open, wasOpen]);

    const problems = lines.filter((l) => l.issue);
    const usingSaved = addressId !== null;
    const chosenArea = usingSaved ? addresses.find((a) => a.id === addressId)?.shipping_area_id : values.shipping_area_id || null;
    const allAreas = governorates.flatMap((g) => g.areas.map((a) => ({ ...a, governorate: g.name })));
    const area = allAreas.find((a) => a.id === chosenArea);

    // Totals come from the server for the chosen area (exact VAT and discount rules).
    const refreshQuote = (id: number | null | '') => {
        if (id && id !== areaId) router.reload({ only: ['quote', 'areaId'], data: { area: id } });
    };

    const blocked = problems.length > 0;
    const missingFields = !usingSaved && !addressComplete(values);
    const cannotContinue = blocked || !area || missingFields || !quote.has_area;
    const why = blocked ? t('checkout.why_problems') : !area ? t('checkout.why_area') : missingFields ? t('checkout.why_fields') : '';

    const place = () => {
        setPlacing(true);
        router.post(
            '/checkout',
            {
                idempotency_key: idempotencyKey,
                payment_method: 'cod',
                address_id: addressId,
                address: usingSaved ? null : addressPayload(values),
                save_address: save,
                customer_note: note || null,
                expected_total: quote.grand_total,
            },
            {
                onError: () => setStep('info'),
                onFinish: () => setPlacing(false),
            },
        );
    };

    const shipToText = usingSaved
        ? addresses.find((a) => a.id === addressId)?.text
        : [`${values.first_name} ${values.last_name}`, `${values.street}, ${values.building}`, area && `${area.name}, ${area.governorate}`, values.phone].filter(Boolean).join(' · ');

    return (
        <div className="store flex min-h-screen flex-wrap bg-white">
            <Head title={t('checkout.title')} />

            <div className="min-w-0 flex-[1_1_600px] border-e border-[#e2e2e2] px-4 pt-10 pb-14 sm:px-12">
                <div className="ms-auto flex max-w-[560px] flex-col gap-7">
                    <header className="flex flex-col items-center gap-4.5">
                        <Link href={`/${locale}`} className="font-logo text-[34px] tracking-[0.06em]">
                            iShop
                        </Link>
                        <nav aria-label={t('checkout.steps')}>
                            <ol className="flex items-center gap-2.5 text-[13px]">
                                <li>
                                    <button type="button" onClick={cart.openDrawer}>
                                        {t('cart.title')}
                                    </button>
                                </li>
                                <li aria-hidden="true">›</li>
                                <li>
                                    {step === 'payment' ? (
                                        <button type="button" onClick={() => setStep('info')}>
                                            {t('checkout.information')}
                                        </button>
                                    ) : (
                                        <span aria-current="step" className="font-medium">
                                            {t('checkout.information')}
                                        </span>
                                    )}
                                </li>
                                <li aria-hidden="true">›</li>
                                <li aria-current={step === 'payment' ? 'step' : undefined} className={step === 'payment' ? 'font-medium' : 'text-[#6b6b6b]'}>
                                    {t('checkout.payment')}
                                </li>
                            </ol>
                        </nav>
                    </header>

                    {blocked && (
                        <section role="alert" className="flex flex-col gap-3 rounded-lg border border-alert bg-[#fdf3f2] px-5 py-4.5">
                            <div className="flex items-start gap-3">
                                <AlertTriangle className="mt-0.5 size-5.5 flex-none text-alert" aria-hidden="true" />
                                <div className="flex flex-col gap-1.5">
                                    <p className="text-base font-medium text-[#8c1d18]">{t('checkout.changed_title')}</p>
                                    <p className="text-sm leading-relaxed text-[#3a3a3a]">{t('checkout.changed_text')}</p>
                                </div>
                            </div>
                            <ul className="flex list-disc flex-col gap-1.5 ps-[3.4rem] text-sm leading-normal">
                                {problems.map((l) => (
                                    <li key={l.id}>
                                        <strong className="font-medium">
                                            {l.name}
                                            {l.options && ` (${l.options})`}
                                        </strong>{' '}
                                        — {l.issue}
                                    </li>
                                ))}
                            </ul>
                            <button type="button" onClick={cart.openDrawer} className="ms-8.5 h-11 self-start rounded-md bg-ink px-5 text-sm text-white">
                                {t('checkout.edit_cart')}
                            </button>
                        </section>
                    )}

                    {step === 'info' ? (
                        <>
                            <section className="flex flex-col gap-3">
                                <h2 className="text-xl font-medium">{t('checkout.contact')}</h2>
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#d9d9d9] px-4 py-3.5 text-sm">
                                    <span>
                                        <span className="text-subtle">{t('checkout.signed_in_as')}</span> <span dir="ltr">{email}</span>
                                    </span>
                                    <Link href="/logout" method="post" as="button" className="text-[13px] underline underline-offset-3">
                                        {t('checkout.sign_out')}
                                    </Link>
                                </div>
                            </section>

                            <section className="flex flex-col gap-3.5">
                                <h2 className="text-xl font-medium">{t('checkout.delivery_address')}</h2>
                                {addresses.length > 0 && (
                                    <fieldset className="overflow-hidden rounded-lg border border-[#d9d9d9]">
                                        <legend className="sr-only">{t('checkout.delivery_address')}</legend>
                                        {addresses.map((a) => (
                                            <label key={a.id} className={`flex cursor-pointer items-start gap-3 border-b border-[#e2e2e2] p-4 ${addressId === a.id ? 'bg-[#f7f7f7]' : ''}`}>
                                                <input
                                                    type="radio"
                                                    name="address"
                                                    checked={addressId === a.id}
                                                    onChange={() => {
                                                        setAddressId(a.id);
                                                        refreshQuote(a.shipping_area_id);
                                                    }}
                                                    className="mt-0.5 size-4.5 flex-none accent-ink"
                                                />
                                                <span className="flex flex-col gap-1 text-sm leading-normal">
                                                    {a.label && <span className="font-medium">{a.label}</span>}
                                                    <span className="text-subtle">{a.text}</span>
                                                </span>
                                            </label>
                                        ))}
                                        <label className={`flex cursor-pointer items-start gap-3 p-4 ${addressId === null ? 'bg-[#f7f7f7]' : ''}`}>
                                            <input type="radio" name="address" checked={addressId === null} onChange={() => setAddressId(null)} className="mt-0.5 size-4.5 flex-none accent-ink" />
                                            <span className="text-sm font-medium">{t('checkout.new_address')}</span>
                                        </label>
                                    </fieldset>
                                )}

                                {!usingSaved && (
                                    <>
                                        <AddressFields
                                            values={values}
                                            onChange={(next) => {
                                                setValues(next);
                                                if (next.shipping_area_id !== values.shipping_area_id) refreshQuote(next.shipping_area_id);
                                            }}
                                            governorates={governorates}
                                            errors={errors}
                                            errorPrefix="address."
                                        />
                                        <label className="flex cursor-pointer items-center gap-2.5 text-sm">
                                            <input type="checkbox" checked={save} onChange={() => setSave(!save)} className="size-4.5 accent-ink" />
                                            {t('checkout.save_address')}
                                        </label>
                                    </>
                                )}

                                <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                                    {t('checkout.note')}
                                    <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={1000} placeholder={t('checkout.note_help')} className="border border-[#d9d9d9] px-3.5 py-2.5 text-[15px] text-ink" />
                                </label>
                            </section>

                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <button type="button" onClick={cart.openDrawer} className="inline-flex items-center gap-2 text-sm">
                                    <ArrowLeft className="size-3.5 rtl:rotate-180" aria-hidden="true" />
                                    {t('checkout.return_to_cart')}
                                </button>
                                <button
                                    type="button"
                                    disabled={cannotContinue}
                                    aria-describedby={cannotContinue ? 'continue-why' : undefined}
                                    onClick={() => setStep('payment')}
                                    className="h-14 rounded-lg bg-ink px-7 text-base font-medium text-white disabled:cursor-not-allowed disabled:bg-[#9a9a9a]"
                                >
                                    {t('checkout.continue')}
                                </button>
                            </div>
                            {cannotContinue && why && (
                                <p id="continue-why" className={`-mt-4 text-end text-[13px] ${blocked ? 'text-alert' : 'text-subtle'}`}>
                                    {why}
                                </p>
                            )}
                        </>
                    ) : (
                        <>
                            <section className="rounded-lg border border-[#d9d9d9] text-sm">
                                {[
                                    { label: t('checkout.contact'), value: email },
                                    { label: t('checkout.ship_to'), value: shipToText, change: true },
                                    { label: t('checkout.delivery'), value: area ? `${area.name} · ${quote.shipping_total === 0 ? t('checkout.free') : money(quote.shipping_total, locale)}` : '', change: true },
                                ].map((row, i) => (
                                    <div key={row.label} className={`flex items-start gap-3 px-4 py-3.5 ${i < 2 ? 'border-b border-[#e2e2e2]' : ''}`}>
                                        <span className="w-[90px] flex-none text-subtle">{row.label}</span>
                                        <span className="min-w-0 flex-1 leading-normal">{row.value}</span>
                                        {row.change && (
                                            <button type="button" onClick={() => setStep('info')} className="text-[13px] underline underline-offset-3">
                                                {t('checkout.change')}
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </section>

                            <section className="flex flex-col gap-3">
                                <h2 className="text-xl font-medium">{t('checkout.payment')}</h2>
                                <p className="-mt-1 text-[13px] text-subtle">{t('checkout.payment_safe')}</p>
                                <fieldset className="overflow-hidden rounded-lg border border-[#d9d9d9]">
                                    <legend className="sr-only">{t('checkout.payment')}</legend>
                                    <label className="flex cursor-pointer items-start gap-3 border-b border-[#e2e2e2] bg-[#f7f7f7] p-4">
                                        <input type="radio" name="method" checked readOnly className="mt-0.5 size-4.5 flex-none accent-ink" />
                                        <span className="flex flex-col gap-1">
                                            <span className="text-[15px] font-medium">{t('checkout.cod')}</span>
                                            <span className="text-[13px] leading-normal text-subtle">{t('checkout.cod_text')}</span>
                                        </span>
                                    </label>
                                    <label className="flex items-start gap-3 p-4 opacity-60">
                                        <input type="radio" name="method" disabled className="mt-0.5 size-4.5 flex-none" />
                                        <span className="flex flex-col gap-1">
                                            <span className="text-[15px] font-medium">{t('checkout.card')}</span>
                                            <span className="text-[13px] text-subtle">{t('checkout.card_soon')}</span>
                                        </span>
                                    </label>
                                </fieldset>
                            </section>

                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <button type="button" onClick={() => setStep('info')} className="inline-flex items-center gap-2 text-sm">
                                    <ArrowLeft className="size-3.5 rtl:rotate-180" aria-hidden="true" />
                                    {t('checkout.return_to_info')}
                                </button>
                                <button type="button" disabled={placing || blocked} onClick={place} className="h-14 rounded-lg bg-ink px-7 text-base font-medium text-white disabled:opacity-60">
                                    {t('checkout.place_order')} · {money(quote.grand_total, locale)}
                                </button>
                            </div>
                            <p className="-mt-3 text-end text-[13px] text-subtle">{t('checkout.cod_note')}</p>
                        </>
                    )}
                </div>
            </div>

            <aside aria-label={t('cart.title')} className="min-w-0 flex-[1_1_440px] bg-[#f5f5f5] px-4 py-10 sm:px-12">
                <div className="flex max-w-[440px] flex-col gap-5.5">
                    <ul className="flex flex-col gap-4.5">
                        {lines.map((l) => {
                            const gone = !!l.issue && l.must_remove;

                            return (
                                <li key={l.id} className={`flex items-start gap-3.5 ${gone ? 'opacity-55' : ''}`}>
                                    <span className="relative flex size-16 flex-none items-center justify-center rounded-lg border border-[#e0e0e0] bg-white">
                                        <ProductImage src={l.image} alt="" swatch={l.swatch} />
                                        <span className="absolute -end-2 -top-2 flex h-5.5 min-w-5.5 items-center justify-center rounded-full bg-ink px-1.5 text-xs text-white">{l.quantity}</span>
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span className="text-sm leading-snug">{l.name}</span>
                                        {l.options && <span className="text-xs text-subtle">{l.options}</span>}
                                        {l.issue && (
                                            <span className="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-alert">
                                                <AlertCircle className="size-3.5" aria-hidden="true" />
                                                {l.issue}
                                            </span>
                                        )}
                                    </span>
                                    <span className={`text-sm whitespace-nowrap ${gone ? 'line-through' : ''}`}>{money(l.unit_price * l.quantity, locale)}</span>
                                </li>
                            );
                        })}
                    </ul>

                    {quote.coupon ? (
                        <p className="inline-flex items-center gap-2 text-[13px]">
                            <span className="rounded bg-[#e6e6e6] px-2.5 py-1 font-medium" dir="ltr">
                                {quote.coupon.code}
                            </span>
                            <button type="button" onClick={() => router.delete('/checkout/coupon', { preserveScroll: true })} className="underline">
                                {t('checkout.remove')}
                            </button>
                        </p>
                    ) : (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (code.trim()) router.post('/checkout/coupon', { code: code.trim() }, { preserveScroll: true, onSuccess: () => setCode('') });
                            }}
                            className="flex flex-col gap-1.5"
                        >
                            <div className="flex gap-2">
                                <label htmlFor="code" className="sr-only">
                                    {t('checkout.code')}
                                </label>
                                <input id="code" value={code} onChange={(e) => setCode(e.target.value)} placeholder={t('checkout.code')} maxLength={50} className="h-12 min-w-0 flex-1 rounded-lg border border-[#d9d9d9] bg-white px-3.5 text-[15px] uppercase" />
                                <button type="submit" className="h-12 rounded-lg border border-[#d9d9d9] bg-[#ececec] px-4.5 text-sm">
                                    {t('checkout.apply')}
                                </button>
                            </div>
                            {errors.code && <span className="text-xs text-alert">{errors.code}</span>}
                        </form>
                    )}
                    {quote.coupon_error && <p className="text-xs text-alert">{quote.coupon_error}</p>}

                    <dl className="flex flex-col gap-2.5 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt>{t('checkout.subtotal', { count: quote.item_count })}</dt>
                            <dd>{money(quote.subtotal, locale)}</dd>
                        </div>
                        {quote.discount_total > 0 && (
                            <div className="flex justify-between gap-3">
                                <dt>{t('checkout.discount')}</dt>
                                <dd>−{money(quote.discount_total, locale)}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-3">
                            <dt>{t('checkout.shipping')}</dt>
                            <dd className={quote.has_area ? '' : 'text-subtle'}>{!quote.has_area ? t('checkout.shipping_tbd') : quote.shipping_total === 0 ? t('checkout.free') : money(quote.shipping_total, locale)}</dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-3 border-t border-[#e0e0e0] pt-3">
                            <dt className="text-lg font-medium">{t('checkout.total')}</dt>
                            <dd className="text-xl font-medium">{money(quote.grand_total, locale)}</dd>
                        </div>
                        <dd className="text-end text-xs text-subtle">{t('checkout.vat', { amount: money(quote.tax_total, locale) })}</dd>
                    </dl>
                </div>
            </aside>
        </div>
    );
}
