import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { useOtherLanguageUrl, useShop, useT } from '@/lib/shop';

/**
 * Footer (approved mockup): shop categories, information pages (the
 * published About pages, so no link leads nowhere), account links and a
 * short about text; language switch instead of a country, payment methods.
 */
export function StoreFooter() {
    const { nav, aboutMenu, locale, auth } = useShop();
    const t = useT();
    const switchUrl = useOtherLanguageUrl();
    const info = aboutMenu?.columns.flatMap((c) => c.links) ?? [];
    const link = 'text-sm text-subtle transition-colors hover:text-ink';

    return (
        <footer className="bg-white px-4 pt-16 pb-8 sm:px-10">
            <div className="mx-auto flex max-w-[1360px] flex-col gap-12">
                <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.5fr]">
                    <div className="flex flex-col gap-3.5">
                        <p className="store-caps mb-1 text-xs">{t('footer.shop')}</p>
                        {nav.map((c) => (
                            <Link key={c.url} href={c.url} className={link}>
                                {c.name}
                            </Link>
                        ))}
                        {nav[0]?.highlights.slice(0, 1).map((h) => (
                            <Link key={h.url} href={h.url} className={link}>
                                {h.name}
                            </Link>
                        ))}
                    </div>

                    {info.length > 0 && (
                        <div className="flex flex-col gap-3.5">
                            <p className="store-caps mb-1 text-xs">{t('footer.information')}</p>
                            {info.map((l) => (
                                <Link key={l.url} href={l.url} className={link}>
                                    {l.name}
                                </Link>
                            ))}
                        </div>
                    )}

                    <div className="flex flex-col gap-3.5">
                        <p className="store-caps mb-1 text-xs">{t('footer.account')}</p>
                        {auth.user ? (
                            <Link href={`/${locale}/account/orders`} className={link}>
                                {t('account_menu.orders')}
                            </Link>
                        ) : (
                            <a href="/login" className={link}>
                                {t('account_menu.sign_in')}
                            </a>
                        )}
                        <Link href={`/${locale}/wishlist`} className={link}>
                            {t('nav.wishlist')}
                        </Link>
                    </div>

                    <div className="flex flex-col gap-3.5">
                        <p className="store-caps mb-1 text-xs">{t('footer.about')}</p>
                        <p className="text-sm leading-relaxed text-subtle">{t('footer.about_text')}</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-5 border-t border-[#e2e2e2] pt-6 text-[13px] text-subtle">
                    <div className="flex items-center gap-2" role="group" aria-label={t('nav.language')}>
                        {(['en', 'ar'] as const).map((l) =>
                            l === locale ? (
                                <span key={l} aria-current="true" className="border border-ink px-3 py-1.5 text-xs text-ink">
                                    {l === 'en' ? 'English' : 'العربية'}
                                </span>
                            ) : (
                                <a key={l} href={switchUrl} lang={l} className={cn('border border-[#e2e2e2] px-3 py-1.5 text-xs transition-colors hover:border-ink hover:text-ink')}>
                                    {l === 'en' ? 'English' : 'العربية'}
                                </a>
                            ),
                        )}
                    </div>
                    <span className="store-caps text-[11px]">{t('footer.copyright', { year: new Date().getFullYear() })}</span>
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="rounded-sm border border-line px-2 py-1 text-[11px]">{t('footer.cod')}</span>
                        <span className="rounded-sm border border-line px-2 py-1 text-[11px] font-semibold">VISA</span>
                        <span className="rounded-sm border border-line px-2 py-1 text-[11px] font-semibold">Mastercard</span>
                    </span>
                </div>
            </div>
        </footer>
    );
}
