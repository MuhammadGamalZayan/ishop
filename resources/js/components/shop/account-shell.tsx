import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useShop, useT } from '@/lib/shop';

/**
 * My account frame from the approved mockup: greeting + side menu, page on
 * the other side. Used by the store's account pages and by the profile /
 * security settings pages that come from the starter kit.
 */
export function AccountShell({ children }: { children: ReactNode }) {
    const { auth, locale } = useShop();
    const t = useT();
    const current = usePage().url;

    const items = [
        { href: `/${locale}/account/orders`, label: t('account.orders') },
        { href: `/${locale}/account/addresses`, label: t('account.addresses') },
        { href: `/${locale}/wishlist`, label: t('account.wishlist') },
        { href: '/settings/profile', label: t('account.profile') },
        { href: '/settings/security', label: t('account.security') },
    ];

    return (
        <div className="mx-auto flex max-w-[1240px] flex-wrap items-start gap-10 px-4 pt-12 pb-24 sm:px-10">
            <nav aria-label={t('account.title')} className="flex max-w-[260px] flex-[1_1_220px] flex-col gap-1">
                <p className="mb-1 text-[13px] text-subtle">{t('account.hello')}</p>
                <p className="store-caps mb-4.5 text-xl">{auth.user?.name}</p>
                {items.map((item) => {
                    const on = current.startsWith(item.href);

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={on ? 'page' : undefined}
                            className={`border-s-2 px-3.5 py-3 text-[15px] ${on ? 'border-ink bg-white' : 'border-transparent'}`}
                        >
                            {item.label}
                        </Link>
                    );
                })}
                <Link href="/logout" method="post" as="button" className="mt-3.5 px-3.5 py-3 text-start text-sm text-subtle">
                    {t('account.sign_out')}
                </Link>
            </nav>
            <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">{children}</div>
        </div>
    );
}

/** Layout wrapper for the starter kit's settings pages (profile, security). */
export function AccountSettingsLayout({ children }: { children: ReactNode }) {
    return (
        <AccountShell>
            <div className="bg-white p-6 sm:p-8">{children}</div>
        </AccountShell>
    );
}
