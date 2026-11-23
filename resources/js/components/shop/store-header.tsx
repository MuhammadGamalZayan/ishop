import { Link, router } from '@inertiajs/react';
import { ChevronDown, Globe, Heart, Menu, Search, ShoppingBag, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useCart } from '@/components/shop/cart-context';
import { Drawer } from '@/components/shop/drawer';
import { otherLanguageUrl, useShop, useT } from '@/lib/shop';
import type { NavCategory } from '@/lib/shop';

type Panel = 'none' | 'menu' | 'search' | 'account';

/**
 * Header from the mockups: category nav with a mega menu, centred logo,
 * language switch, account, search and cart. On phones the categories move
 * into a side menu. (The announcement bar needs an admin setting first.)
 */
export function StoreHeader() {
    const { nav, locale, cart, auth, wishlist } = useShop();
    const t = useT();
    const cartUi = useCart();
    const [panel, setPanel] = useState<Panel>('none');
    const [menuFor, setMenuFor] = useState<NavCategory | null>(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const headerRef = useRef<HTMLElement>(null);

    const close = () => {
        setPanel('none');
        setMenuFor(null);
    };

    // Close panels on Esc, on outside click, and when the page changes.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close();
        const onClick = (e: MouseEvent) => headerRef.current && !headerRef.current.contains(e.target as Node) && close();
        const off = router.on('navigate', close);
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);

        return () => {
            off();
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    }, []);

    const toggle = (p: Panel) => setPanel((current) => (current === p ? 'none' : p));
    const home = `/${locale}`;

    return (
        <header ref={headerRef} className="relative z-40 bg-white">
            <div className="relative border-b border-[#e2e2e2]">
                <div className="grid min-h-20 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-5 px-4 py-3 sm:px-10">
                    <nav aria-label={t('nav.main')} className="flex items-center">
                        <button type="button" onClick={() => setMobileOpen(true)} aria-label={t('nav.menu')} className="-ms-3 flex size-11 items-center justify-center lg:hidden">
                            <Menu className="size-5" strokeWidth={1.3} />
                        </button>
                        <ul className="store-caps hidden flex-wrap gap-7 text-[13px] lg:flex">
                            {nav.map((category) =>
                                category.children.length > 0 ? (
                                    <li key={category.url}>
                                        <button
                                            type="button"
                                            aria-expanded={menuFor?.url === category.url}
                                            onClick={() => {
                                                setPanel(menuFor?.url === category.url ? 'none' : 'menu');
                                                setMenuFor(menuFor?.url === category.url ? null : category);
                                            }}
                                            className={`store-caps -mb-7 inline-flex items-center gap-1 border-b pb-7 ${menuFor?.url === category.url ? 'border-ink' : 'border-transparent'}`}
                                        >
                                            {category.name}
                                            <ChevronDown className="size-3" aria-hidden="true" />
                                        </button>
                                    </li>
                                ) : (
                                    <li key={category.url}>
                                        <Link href={category.url}>{category.name}</Link>
                                    </li>
                                ),
                            )}
                        </ul>
                    </nav>

                    <Link href={home} className="font-logo text-[30px] leading-none tracking-[0.06em]">
                        iShop
                    </Link>

                    <div className="flex items-center justify-end gap-0.5">
                        <a href={typeof window === 'undefined' ? '#' : otherLanguageUrl(locale)} lang={locale === 'ar' ? 'en' : 'ar'} className="hidden h-11 items-center gap-2 px-2.5 text-[13px] sm:inline-flex">
                            <Globe className="size-[18px]" strokeWidth={1.3} aria-hidden="true" />
                            <span>{t('nav.language')}</span>
                        </a>
                        <Link href={`/${locale}/wishlist`} aria-label={`${t('nav.wishlist')}${wishlist.length ? ` (${wishlist.length})` : ''}`} className="relative hidden size-11 items-center justify-center sm:flex">
                            <Heart className="size-[21px]" strokeWidth={1.3} />
                            {wishlist.length > 0 && <span className="absolute end-1 top-2 size-2 rounded-full bg-ink" aria-hidden="true" />}
                        </Link>
                        <button type="button" onClick={() => toggle('account')} aria-label={t('nav.account')} aria-expanded={panel === 'account'} className="flex size-11 items-center justify-center">
                            <User className="size-[21px]" strokeWidth={1.3} />
                        </button>
                        <button type="button" onClick={() => toggle('search')} aria-label={t('nav.search')} aria-expanded={panel === 'search'} className="flex size-11 items-center justify-center">
                            <Search className="size-[21px]" strokeWidth={1.3} />
                        </button>
                        <button type="button" onClick={cartUi.openDrawer} aria-label={`${t('nav.cart')}${cart.count ? ` (${cart.count})` : ''}`} className="relative flex size-11 items-center justify-center">
                            <ShoppingBag className="size-[22px]" strokeWidth={1.3} />
                            {cart.count > 0 && (
                                <span className="absolute end-0.5 top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-ink px-1 text-[11px] text-white">{cart.count}</span>
                            )}
                        </button>
                    </div>
                </div>

                {(panel === 'menu' || panel === 'search') && <button type="button" aria-label={t('nav.close')} onClick={close} className="absolute inset-x-0 top-full h-screen cursor-default bg-black/40" />}

                {panel === 'menu' && menuFor && (
                    <div className="absolute inset-x-0 top-full border-b border-[#e2e2e2] bg-white px-10 pt-10 pb-12">
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-x-10 gap-y-3.5">
                            <p className="store-caps col-span-full mb-1 text-xs">{menuFor.name}</p>
                            {menuFor.children.map((child) => (
                                <Link key={child.url} href={child.url} className="text-[15px] text-subtle hover:text-ink">
                                    {child.name}
                                </Link>
                            ))}
                            <Link href={menuFor.url} className="text-[15px] text-ink underline underline-offset-4">
                                {t('nav.shop_all')}
                            </Link>
                        </div>
                    </div>
                )}

                {panel === 'search' && (
                    <div className="absolute inset-x-0 top-full border-b border-[#e2e2e2] bg-white">
                        <form
                            role="search"
                            action={`/${locale}/all`}
                            onSubmit={(e) => {
                                e.preventDefault();
                                const q = new FormData(e.currentTarget).get('q')?.toString().trim();
                                if (q) router.get(`/${locale}/all`, { q });
                            }}
                            className="flex h-19 items-center gap-4 px-4 sm:px-10"
                        >
                            <label htmlFor="site-search" className="flex">
                                <Search className="size-5" strokeWidth={1.3} aria-hidden="true" />
                                <span className="sr-only">{t('nav.search')}</span>
                            </label>
                            <input id="site-search" name="q" type="search" autoFocus maxLength={100} placeholder={t('search.placeholder')} className="store-caps h-11 min-w-0 flex-1 bg-transparent text-lg outline-none" />
                            <button type="button" onClick={close} aria-label={t('nav.close')} className="flex size-11 items-center justify-center">
                                <X className="size-[18px]" strokeWidth={1.3} />
                            </button>
                        </form>
                    </div>
                )}

                {panel === 'account' && (
                    <div role="dialog" aria-label={t('nav.account')} className="absolute end-4 top-[calc(100%+8px)] flex w-[340px] max-w-[calc(100vw-32px)] flex-col gap-3.5 bg-white p-5.5 shadow-[0_12px_32px_rgba(0,0,0,0.14)] sm:end-10">
                        {auth.user ? (
                            <>
                                <p className="text-base">{t('account_menu.hello', { name: auth.user.name })}</p>
                                <div className="grid grid-cols-2 gap-2.5">
                                    <Link href={`/${locale}/account/orders`} className="flex h-11 items-center justify-center border border-[#d6d6d6] text-sm">
                                        {t('account_menu.orders')}
                                    </Link>
                                    <Link href={`/${locale}/account/addresses`} className="flex h-11 items-center justify-center border border-[#d6d6d6] text-sm">
                                        {t('account_menu.profile')}
                                    </Link>
                                </div>
                                <Link href="/logout" method="post" as="button" className="store-caps flex h-11.5 items-center justify-center border border-ink text-[13px]">
                                    {t('account_menu.sign_out')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <p className="text-base">{t('account_menu.title')}</p>
                                <a href="/login" className="store-caps flex h-11.5 items-center justify-center bg-ink text-[13px] text-white">
                                    {t('account_menu.sign_in')}
                                </a>
                                <a href="/register" className="store-caps flex h-11.5 items-center justify-center border border-ink text-[13px]">
                                    {t('account_menu.create')}
                                </a>
                                <p className="text-xs leading-relaxed text-subtle">{t('account_menu.guest_note')}</p>
                            </>
                        )}
                    </div>
                )}
            </div>

            <Drawer open={mobileOpen} onOpenChange={setMobileOpen} title={t('nav.menu')}>
                <nav aria-label={t('nav.main')} className="flex flex-col px-7 py-4">
                    {nav.map((category) => (
                        <div key={category.url} className="border-b border-line py-3">
                            <Link href={category.url} onClick={() => setMobileOpen(false)} className="store-caps block py-2 text-sm">
                                {category.name}
                            </Link>
                            {category.children.map((child) => (
                                <Link key={child.url} href={child.url} onClick={() => setMobileOpen(false)} className="block py-1.5 ps-3 text-[15px] text-subtle">
                                    {child.name}
                                </Link>
                            ))}
                        </div>
                    ))}
                    <a href={typeof window === 'undefined' ? '#' : otherLanguageUrl(locale)} className="mt-4 inline-flex items-center gap-2 py-2 text-sm">
                        <Globe className="size-4" strokeWidth={1.3} aria-hidden="true" />
                        {t('nav.language')}
                    </a>
                </nav>
            </Drawer>
        </header>
    );
}
