import { Link, router } from '@inertiajs/react';
import { ChevronDown, Globe, Heart, Menu, Search, ShoppingBag, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useCart } from '@/components/shop/cart-context';
import { Drawer } from '@/components/shop/drawer';
import { cn } from '@/lib/utils';
import { useOtherLanguageUrl, useShop, useT } from '@/lib/shop';
import type { NavLink, PictureCard } from '@/lib/shop';

type Panel = 'none' | 'menu' | 'search' | 'account';

/** One mega menu: link columns on the start side, picture cards on the end side. */
type Mega = {
    key: string;
    name: string;
    url?: string;
    columns: { title: string; links: NavLink[]; swatches?: Record<string, string | null> }[];
    cards: PictureCard[];
};

/**
 * Header (approved mockup): Women / Men / About with mega menus, centred
 * logo, language, wishlist, account, search and cart.
 * - Desktop: a menu opens on hover (short delay so passing over doesn't
 *   flash it) or on click / Enter for touch and keyboard users.
 * - It sticks to the top; after scrolling it shrinks into a rounded bar
 *   inside the page width.
 * - Phones: a side drawer with each menu as an expandable section.
 */
export function StoreHeader() {
    const { nav, aboutMenu, locale, cart, auth, wishlist } = useShop();
    const t = useT();
    const switchUrl = useOtherLanguageUrl();
    const cartUi = useCart();
    const [panel, setPanel] = useState<Panel>('none');
    const [openKey, setOpenKey] = useState<string | null>(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const headerRef = useRef<HTMLElement>(null);
    const hoverTimer = useRef<number | undefined>(undefined);

    // Menus: each category's columns, then popular colours and highlights, then About.
    const menus: Mega[] = [
        ...nav.map((c) => ({
            key: c.url,
            name: c.name,
            url: c.url,
            columns: [
                ...c.columns,
                ...(c.colours.length ? [{ title: t('menu.popular_colours'), links: c.colours.map(({ name, url }) => ({ name, url })), swatches: Object.fromEntries(c.colours.map((s) => [s.url, s.hex])) }] : []),
                ...(c.highlights.length ? [{ title: t('menu.highlights'), links: c.highlights }] : []),
            ],
            cards: c.cards,
        })),
        ...(aboutMenu ? [{ key: 'about', name: t('menu.about'), columns: aboutMenu.columns, cards: aboutMenu.cards }] : []),
    ];
    const open = menus.find((m) => m.key === openKey) ?? null;

    const close = () => {
        window.clearTimeout(hoverTimer.current);
        setPanel('none');
        setOpenKey(null);
    };

    const openMenu = (key: string, delay = 0) => {
        window.clearTimeout(hoverTimer.current);
        hoverTimer.current = window.setTimeout(() => {
            setOpenKey(key);
            setPanel('menu');
        }, delay);
    };

    const leave = () => {
        window.clearTimeout(hoverTimer.current);
        hoverTimer.current = window.setTimeout(() => {
            setPanel((p) => (p === 'menu' ? 'none' : p));
            setOpenKey(null);
        }, 180);
    };

    // Close on Esc, outside click and page change; track scrolling for the compact bar.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close();
        const onClick = (e: MouseEvent) => headerRef.current && !headerRef.current.contains(e.target as Node) && close();
        const onScroll = () => setScrolled(window.scrollY > 120);
        const off = router.on('navigate', close);
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        return () => {
            off();
            window.clearTimeout(hoverTimer.current);
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
            window.removeEventListener('scroll', onScroll);
        };
    }, []);

    const toggle = (p: Panel) => {
        setOpenKey(null);
        setPanel((current) => (current === p ? 'none' : p));
    };
    const home = `/${locale}`;

    return (
        <header ref={headerRef} className={cn('sticky top-0 z-40 transition-[padding] duration-300', scrolled && 'lg:px-6 lg:pt-2.5')}>
            <div
                className={cn(
                    'relative border-b border-[#e2e2e2] bg-white transition-all duration-300',
                    scrolled && 'lg:mx-auto lg:max-w-[1280px] lg:rounded-2xl lg:border lg:shadow-[0_8px_30px_rgba(0,0,0,0.07)]',
                )}
                onMouseLeave={leave}
            >
                <div className={cn('grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-5 px-4 transition-all duration-300 sm:px-10', scrolled ? 'min-h-16 lg:min-h-15' : 'min-h-20')}>
                    <nav aria-label={t('nav.main')} className="flex items-center">
                        <button type="button" onClick={() => setMobileOpen(true)} aria-label={t('nav.menu')} className="-ms-3 flex size-11 items-center justify-center lg:hidden">
                            <Menu className="size-5" strokeWidth={1.3} />
                        </button>
                        <ul className="store-caps hidden flex-wrap gap-8 text-[13px] lg:flex">
                            {menus.map((menu) => {
                                const isOpen = openKey === menu.key;

                                return (
                                    <li key={menu.key} onMouseEnter={() => openMenu(menu.key, panel === 'menu' ? 0 : 90)}>
                                        <button
                                            type="button"
                                            aria-expanded={isOpen}
                                            aria-haspopup="true"
                                            onClick={() => (isOpen ? close() : openMenu(menu.key))}
                                            className={cn(
                                                'store-caps relative inline-flex items-center gap-1 transition-[padding] duration-300 after:absolute after:inset-x-0 after:h-px after:origin-center after:bg-ink after:transition-transform after:duration-300',
                                                scrolled ? 'py-5 after:bottom-3.5' : 'py-7 after:bottom-5',
                                                isOpen ? 'after:scale-x-100' : 'after:scale-x-0 hover:after:scale-x-100',
                                            )}
                                        >
                                            {menu.name}
                                            <ChevronDown className={cn('size-3 transition-transform duration-200', isOpen && 'rotate-180')} aria-hidden="true" />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>

                    <Link href={home} className={cn('font-logo leading-none tracking-[0.06em] transition-all duration-300', scrolled ? 'text-[26px]' : 'text-[30px]')}>
                        iShop
                    </Link>

                    <div className="flex items-center justify-end gap-0.5">
                        <a href={switchUrl} lang={locale === 'ar' ? 'en' : 'ar'} className="hidden h-11 items-center gap-2 px-2.5 text-[13px] sm:inline-flex">
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

                {panel === 'search' && <button type="button" aria-label={t('nav.close')} onClick={close} className="absolute inset-x-0 top-full h-screen cursor-default bg-black/40" />}

                {/* Mega menu (desktop) */}
                <div
                    className={cn(
                        'absolute inset-x-0 top-full hidden border-b border-[#e2e2e2] bg-white transition-all duration-250 lg:block',
                        scrolled && 'rounded-b-2xl border-x',
                        panel === 'menu' && open ? 'visible translate-y-0 opacity-100' : 'invisible -translate-y-1.5 opacity-0',
                    )}
                    onMouseEnter={() => window.clearTimeout(hoverTimer.current)}
                >
                    {open && (
                        <div className="mx-auto flex max-w-[1360px] justify-between gap-12 px-10 pt-9 pb-11">
                            <div className="flex flex-wrap gap-x-14 gap-y-8">
                                {open.columns.map((column) => (
                                    <div key={column.title} className="min-w-[140px]">
                                        <p className="store-caps mb-4 text-xs">{column.title}</p>
                                        <ul className="flex flex-col gap-2.5">
                                            {column.links.map((link) => (
                                                <li key={link.url}>
                                                    <Link href={link.url} className="inline-flex items-center gap-2 text-[15px] text-subtle transition-colors hover:text-ink">
                                                        {column.swatches && (
                                                            <span className="inline-block size-2.5 rounded-full border border-black/15" style={{ background: column.swatches[link.url] ?? '#ccc' }} aria-hidden="true" />
                                                        )}
                                                        {link.name}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                            {open.cards.length > 0 && (
                                <div className="flex shrink-0 gap-6">
                                    {open.cards.map((card) => (
                                        <MenuCard key={card.url + card.title} card={card} />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>

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
                <nav aria-label={t('nav.main')} className="flex flex-col px-7 py-2">
                    {menus.map((menu) => (
                        <details key={menu.key} className="group border-b border-line">
                            <summary className="store-caps flex cursor-pointer list-none items-center justify-between py-4 text-sm [&::-webkit-details-marker]:hidden">
                                {menu.name}
                                <ChevronDown className="size-4 transition-transform group-open:rotate-180" strokeWidth={1.3} aria-hidden="true" />
                            </summary>
                            <div className="flex flex-col gap-4 pb-5">
                                {menu.columns.map((column) => (
                                    <div key={column.title}>
                                        <p className="store-caps mb-2 text-[11px] text-subtle">{column.title}</p>
                                        {column.links.map((link) => (
                                            <Link key={link.url} href={link.url} onClick={() => setMobileOpen(false)} className="flex items-center gap-2 py-1.5 text-[15px]">
                                                {column.swatches && <span className="inline-block size-2.5 rounded-full border border-black/15" style={{ background: column.swatches[link.url] ?? '#ccc' }} aria-hidden="true" />}
                                                {link.name}
                                            </Link>
                                        ))}
                                    </div>
                                ))}
                                {menu.url && (
                                    <Link href={menu.url} onClick={() => setMobileOpen(false)} className="text-[15px] underline underline-offset-4">
                                        {t('nav.shop_all')}
                                    </Link>
                                )}
                            </div>
                        </details>
                    ))}
                    <a href={switchUrl} className="mt-4 inline-flex items-center gap-2 py-2 text-sm">
                        <Globe className="size-4" strokeWidth={1.3} aria-hidden="true" />
                        {t('nav.language')}
                    </a>
                </nav>
            </Drawer>
        </header>
    );
}

function MenuCard({ card }: { card: PictureCard }) {
    return (
        <Link href={card.url} className="group/card block w-[230px] text-center xl:w-[260px]">
            <span className="block aspect-[4/5] overflow-hidden bg-mist">
                {card.image && <img src={card.image} alt="" loading="lazy" className="h-full w-full object-cover transition-transform duration-700 group-hover/card:scale-[1.04]" />}
            </span>
            <span className="store-caps mt-3.5 block text-xs">{card.title}</span>
            {card.subtitle && <span className="store-caps mt-1 block text-[11px] text-subtle">{card.subtitle}</span>}
        </Link>
    );
}
