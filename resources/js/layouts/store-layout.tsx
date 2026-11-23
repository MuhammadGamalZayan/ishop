import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { CartDrawer } from '@/components/shop/cart-drawer';
import { CartProvider } from '@/components/shop/cart-context';
import { QuickAdd } from '@/components/shop/quick-add';
import { StoreFooter } from '@/components/shop/store-footer';
import { StoreHeader } from '@/components/shop/store-header';
import { initializeTheme } from '@/hooks/use-appearance';
import { useShop } from '@/lib/shop';

/** Every storefront page: header, page, footer, cart drawer. Always the light design. */
export default function StoreLayout({ children }: { children: ReactNode }) {
    const { locale, dir } = useShop();

    useEffect(() => {
        const html = document.documentElement;
        html.classList.remove('dark');
        html.lang = locale;
        html.dir = dir;

        // Leaving the store (e.g. to account settings): restore the visitor's appearance choice.
        return () => initializeTheme();
    }, [locale, dir]);

    return (
        <CartProvider>
            <div className="store flex min-h-screen flex-col bg-mist" lang={locale} dir={dir}>
                <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:bg-white focus:px-4 focus:py-2">
                    Skip to content
                </a>
                <StoreHeader />
                <main id="main" className="flex-1">
                    {children}
                </main>
                <StoreFooter />
                <CartDrawer />
                <QuickAdd />
            </div>
        </CartProvider>
    );
}
