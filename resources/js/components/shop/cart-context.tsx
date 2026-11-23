import { router } from '@inertiajs/react';
import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

type CartUi = {
    open: boolean;
    openDrawer: () => void;
    closeDrawer: () => void;
    /** Add a variant, then open the drawer (agreed: the drawer is the confirmation, no extra toast). */
    add: (variantId: number, quantity?: number, onAdded?: () => void) => void;
    /** The variant being added right now (its button shows a spinner), else null. */
    adding: number | null;
    /** Product whose quick-add window is open (its /quick URL), else null. */
    quickUrl: string | null;
    openQuick: (productUrl: string) => void;
    closeQuick: () => void;
};

const CartContext = createContext<CartUi | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [adding, setAdding] = useState<number | null>(null);
    const [quickUrl, setQuickUrl] = useState<string | null>(null);

    const openDrawer = useCallback(() => {
        setOpen(true);
        // The drawer's lines are an optional prop: fetched only now, with fresh prices and stock.
        router.reload({ only: ['cartDrawer', 'cart'] });
    }, []);

    const closeDrawer = useCallback(() => setOpen(false), []);

    const add = useCallback((variantId: number, quantity = 1, onAdded?: () => void) => {
        setAdding(variantId);
        router.post(
            '/cart/items',
            { variant_id: variantId, quantity },
            {
                preserveScroll: true,
                preserveState: true,
                // The redirect after adding brings the drawer's lines too: one round trip, drawer opens filled.
                only: ['cart', 'cartDrawer'],
                onSuccess: (page) => {
                    // A refusal (sold out…) comes back as a toast, not as an error: only open on a real add.
                    const flash = (page as unknown as { flash?: { toast?: { type: string } } }).flash;
                    if (flash?.toast?.type !== 'error') {
                        onAdded?.();
                        setQuickUrl(null);
                        setOpen(true);
                    }
                },
                onFinish: () => setAdding(null),
            },
        );
    }, []);

    const value = useMemo(
        () => ({
            open,
            openDrawer,
            closeDrawer,
            add,
            adding,
            quickUrl,
            openQuick: (productUrl: string) => setQuickUrl(`${productUrl}/quick`),
            closeQuick: () => setQuickUrl(null),
        }),
        [open, openDrawer, closeDrawer, add, adding, quickUrl],
    );

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartUi {
    const ctx = useContext(CartContext);

    if (!ctx) {
        throw new Error('useCart must be used inside the store layout.');
    }

    return ctx;
}
