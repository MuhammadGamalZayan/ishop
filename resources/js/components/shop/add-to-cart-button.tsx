import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCart } from '@/components/shop/cart-context';

/**
 * Every "Add to cart": shows a spinner and stays disabled from the click
 * until the item is in the cart (and the drawer opens). Also stops double
 * clicks from adding twice.
 */
export function AddToCartButton({
    variantId,
    ready = true,
    label,
    className,
    ariaLabel,
    onAdded,
}: {
    variantId: number | null;
    ready?: boolean;
    label: ReactNode;
    className?: string;
    ariaLabel?: string;
    onAdded?: () => void;
}) {
    const cart = useCart();
    const loading = variantId !== null && cart.adding === variantId;

    return (
        <button
            type="button"
            disabled={!ready || variantId === null || cart.adding !== null}
            aria-busy={loading}
            aria-label={ariaLabel}
            onClick={() => variantId !== null && cart.add(variantId, 1, onAdded)}
            className={`inline-flex items-center justify-center gap-2 ${className ?? ''}`}
        >
            {loading && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
            {label}
        </button>
    );
}
