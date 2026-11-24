import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import type { Stripe, StripeElementLocale } from '@stripe/stripe-js';
import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { MutableRefObject } from 'react';
import { useT } from '@/lib/shop';

export type StripeConfig = { key: string; test: boolean; currency: string };

/** What the checkout's "Pay now" calls: create (or reuse) the order, then confirm the card. */
export type CardPay = (createIntent: () => Promise<{ client_secret: string; return_url: string }>) => Promise<{ error?: string; returnUrl?: string }>;

// One Stripe.js instance per key and language for the whole visit.
const instances = new Map<string, Promise<Stripe | null>>();
function stripeFor(key: string, locale: StripeElementLocale): Promise<Stripe | null> {
    const id = `${key}|${locale}`;
    if (!instances.has(id)) instances.set(id, loadStripe(key, { locale }));

    return instances.get(id)!;
}

/**
 * Stripe's Payment Element (card only) inside the checkout. The card
 * fields are Stripe's own iframe: card numbers never reach our server.
 * Deferred intent: the order and PaymentIntent are created only when the
 * customer presses Pay, so an abandoned page holds no stock.
 */
export function CardPayment({ config, amount, locale, payRef }: { config: StripeConfig; amount: number; locale: 'en' | 'ar'; payRef: MutableRefObject<CardPay | null> }) {
    return (
        <div className="flex flex-col gap-3">
            <Elements
                stripe={stripeFor(config.key, locale)}
                options={{
                    mode: 'payment',
                    amount,
                    currency: config.currency,
                    // Payment methods come from the Stripe Dashboard, like the PaymentIntent's
                    // automatic_payment_methods (a client-side filter can't be combined with it).
                    locale,
                    appearance: {
                        theme: 'stripe',
                        variables: { colorPrimary: '#1a1a1a', colorText: '#1a1a1a', borderRadius: '6px', fontSizeBase: '15px' },
                    },
                }}
            >
                <CardFields payRef={payRef} />
            </Elements>
            {config.test && <TestCards />}
        </div>
    );
}

function CardFields({ payRef }: { payRef: MutableRefObject<CardPay | null> }) {
    const stripe = useStripe();
    const elements = useElements();
    const t = useT();
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        payRef.current =
            stripe && elements
                ? async (createIntent) => {
                      // Check the fields first: nothing is created for an incomplete card.
                      const { error: fieldError } = await elements.submit();
                      if (fieldError) return { error: fieldError.message };

                      const { client_secret, return_url } = await createIntent();

                      // 3D Secure opens in a Stripe window on this page; no full redirect for cards.
                      const { error } = await stripe.confirmPayment({ elements, clientSecret: client_secret, confirmParams: { return_url }, redirect: 'if_required' });

                      return error ? { error: error.message ?? t('checkout.card_failed') } : { returnUrl: return_url };
                  }
                : null;

        return () => {
            payRef.current = null;
        };
    }, [stripe, elements, payRef, t]);

    return (
        <div className="min-h-[120px]">
            {failed ? (
                // Blocked script, network, or bad keys: say so instead of loading forever.
                <p role="alert" className="text-[13px] text-alert">
                    {t('checkout.card_unavailable')}
                </p>
            ) : (
                !ready && <p className="text-[13px] text-subtle">{t('checkout.card_loading')}</p>
            )}
            <PaymentElement onReady={() => setReady(true)} onLoadError={() => setFailed(true)} options={{ layout: 'tabs' }} />
        </div>
    );
}

const TEST_CARDS = [
    { label: 'checkout.test_success', number: '4242 4242 4242 4242' },
    { label: 'checkout.test_3ds', number: '4000 0025 0000 3155' },
    { label: 'checkout.test_declined', number: '4000 0000 0000 9995' },
] as const;

/** Stripe's published test cards, shown only while test keys are in use. */
function TestCards() {
    const t = useT();
    const [copied, setCopied] = useState<string | null>(null);

    const copy = async (number: string) => {
        try {
            await navigator.clipboard.writeText(number.replaceAll(' ', ''));
            setCopied(number);
            setTimeout(() => setCopied(null), 1500);
        } catch {
            // Clipboard blocked: the number is on screen to type.
        }
    };

    return (
        <div className="rounded-md border border-dashed border-[#b9873a] bg-[#fff8ec] px-3.5 py-3 text-[13px]" role="note">
            <p className="mb-2 font-medium text-[#7a4d00]">{t('checkout.test_title')}</p>
            <ul className="flex flex-col gap-1">
                {TEST_CARDS.map((card) => (
                    <li key={card.number} className="flex flex-wrap items-center justify-between gap-2">
                        <span>{t(card.label)}</span>
                        <span className="flex items-center gap-2">
                            <code dir="ltr" className="font-mono text-[13px]">
                                {card.number}
                            </code>
                            <button
                                type="button"
                                onClick={() => copy(card.number)}
                                className="inline-flex items-center gap-1 rounded border border-[#c9a36a] bg-white px-2 py-0.5 text-[11px]"
                            >
                                {copied === card.number ? <Check className="size-3" aria-hidden="true" /> : <Copy className="size-3" aria-hidden="true" />}
                                {copied === card.number ? t('checkout.copied') : t('checkout.copy')}
                            </button>
                        </span>
                    </li>
                ))}
            </ul>
            <p className="mt-2 text-[#6b5a3a]">{t('checkout.test_rest')}</p>
        </div>
    );
}
