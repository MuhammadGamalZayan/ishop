import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { CartDrawer } from '@/components/shop/cart-drawer';
import { CartProvider } from '@/components/shop/cart-context';
import { initializeTheme } from '@/hooks/use-appearance';
import { useShop } from '@/lib/shop';

/** Checkout: no store header/footer (approved mockup), but the cart drawer stays available. */
export default function CheckoutLayout({ children }: { children: ReactNode }) {
    const { locale, dir } = useShop();

    useEffect(() => {
        const html = document.documentElement;
        html.classList.remove('dark');
        html.lang = locale;
        html.dir = dir;

        return () => initializeTheme();
    }, [locale, dir]);

    return (
        <CartProvider>
            <div lang={locale} dir={dir}>
                {children}
                <CartDrawer />
            </div>
        </CartProvider>
    );
}
