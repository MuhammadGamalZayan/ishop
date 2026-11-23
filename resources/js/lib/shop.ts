import { usePage } from '@inertiajs/react';

/** Shared props every storefront page receives (see HandleInertiaRequests). */
export type ShopProps = {
    locale: 'en' | 'ar';
    dir: 'ltr' | 'rtl';
    t: Record<string, unknown>;
    nav: NavCategory[];
    cart: { count: number };
    wishlist: number[];
    cartDrawer?: CartDrawerData;
    auth: { user: { name: string; email: string } | null };
    name: string;
};

export type NavCategory = { name: string; url: string; children: { name: string; url: string }[] };

export type ProductCardData = {
    id: number;
    name: string;
    url: string;
    price: number;
    compare_at: number | null;
    price_varies: boolean;
    image: string | null;
    image_hover: string | null;
    swatch: string | null;
    colours: number;
    badge: 'new' | 'best' | 'sale' | 'sold_out' | null;
    in_stock: boolean;
    quick_variant_id: number | null;
};

export type CartLine = {
    id: number;
    name: string;
    url: string | null;
    options: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    image: string | null;
    swatch: string | null;
    max: number;
    issue: string | null;
    must_remove: boolean;
};

export type CartDrawerData = {
    lines: CartLine[];
    count: number;
    subtotal: number;
    has_issues: boolean;
    upsell: ProductCardData | null;
};

export function useShop() {
    return usePage<ShopProps>().props;
}

/**
 * Translation lookup into lang/{en,ar}/shop.php: t('cart.title'),
 * t('footer.copyright', { year: 2026 }). Returns the key if missing,
 * so a gap is visible instead of silently empty.
 */
export function useT() {
    const { t } = useShop();

    return (key: string, replace: Record<string, string | number> = {}): string => {
        const value = key.split('.').reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined), t);

        if (typeof value !== 'string') {
            return key;
        }

        return Object.entries(replace).reduce((text, [name, v]) => text.replaceAll(`:${name}`, String(v)), value);
    };
}

/**
 * For the starter kit's account/auth pages, written in English: tr('Password')
 * returns the Arabic from shop.php `ui` when the page is in Arabic, else the
 * English text itself (so nothing goes blank if a phrase isn't translated yet).
 */
export function useTr() {
    const { t, locale } = useShop();
    const ui = (t.ui ?? {}) as Record<string, string>;

    return (english: string): string => (locale === 'ar' && ui[english]) || english;
}

/** Piastres → "EGP 1,250" / "1,250 ج.م" (decimals only when there are any). */
export function money(piastres: number, locale: 'en' | 'ar'): string {
    const pounds = piastres / 100;
    const text = pounds.toLocaleString('en-US', {
        minimumFractionDigits: Number.isInteger(pounds) ? 0 : 2,
        maximumFractionDigits: 2,
    });

    return locale === 'ar' ? `${text} ج.م` : `EGP ${text}`;
}

/** Same page in the other language (full page load so <html dir> and fonts switch cleanly). */
export function otherLanguageUrl(locale: 'en' | 'ar'): string {
    const other = locale === 'ar' ? 'en' : 'ar';
    const { pathname, search } = window.location;

    // Pages without a language prefix (sign in, settings) switch through /locale/{lang}, which comes back here.
    return /^\/(en|ar)(\/|$)/.test(pathname) ? pathname.replace(/^\/(en|ar)/, `/${other}`) + search : `/locale/${other}`;
}
