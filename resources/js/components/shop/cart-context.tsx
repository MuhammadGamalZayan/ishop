import { router } from '@inertiajs/react';
import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

type CartUi = {
    open: boolean;
    openDrawer: () => void;
    closeDrawer: () => void;
    /** Add a variant, then open the drawer (agreed: the drawer is the confirmation, no extra toast). */
    add: (variantId: number, quantity?: number) => void;
    busy: boolean;
};

const CartContext = createContext<CartUi | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const openDrawer = useCallback(() => {
        setOpen(true);
        // The drawer's lines are an optional prop: fetched only now, with fresh prices and stock.
        router.reload({ only: ['cartDrawer', 'cart'] });
    }, []);

    const closeDrawer = useCallback(() => setOpen(false), []);

    const add = useCallback(
        (variantId: number, quantity = 1) => {
            setBusy(true);
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
                            setOpen(true);
                        }
                    },
                    onFinish: () => setBusy(false),
                },
            );
        },
        [],
    );

    const value = useMemo(() => ({ open, openDrawer, closeDrawer, add, busy }), [open, openDrawer, closeDrawer, add, busy]);

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartUi {
    const ctx = useContext(CartContext);

    if (!ctx) {
        throw new Error('useCart must be used inside the store layout.');
    }

    return ctx;
}
