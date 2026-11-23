import { Link } from '@inertiajs/react';
import { useShop, useT } from '@/lib/shop';

/** Footer: categories, payment methods, VAT note. Info pages and newsletter come once they exist. */
export function StoreFooter() {
    const { nav } = useShop();
    const t = useT();

    return (
        <footer className="border-t border-[#e2e2e2] bg-white px-4 pt-16 pb-8 sm:px-10">
            <div className="mx-auto flex max-w-[1280px] flex-col gap-12">
                <div className="flex flex-wrap gap-12">
                    <div className="flex flex-[1_1_220px] flex-col gap-4">
                        <span className="font-logo text-2xl tracking-[0.06em]">iShop</span>
                        <p className="max-w-xs text-sm leading-relaxed text-subtle">{t('home.title')}</p>
                    </div>
                    <div className="flex flex-[1_1_180px] flex-col gap-3.5">
                        <p className="store-caps mb-1 text-xs">{t('footer.shop')}</p>
                        {nav.map((c) => (
                            <Link key={c.url} href={c.url} className="text-sm text-subtle hover:text-ink">
                                {c.name}
                            </Link>
                        ))}
                    </div>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4 border-t border-[#e2e2e2] pt-6 text-[13px] text-subtle">
                    <span>{t('footer.copyright', { year: new Date().getFullYear() })}</span>
                    <span className="flex flex-wrap items-center gap-2.5">
                        <span className="border border-line px-2.5 py-1 text-xs">{t('footer.cod')}</span>
                        <span className="border border-line px-2.5 py-1 text-xs">{t('footer.cards')}</span>
                        <span className="border border-line px-2.5 py-1 text-xs">{t('footer.wallets')}</span>
                    </span>
                </div>
            </div>
        </footer>
    );
}
