import type { ReactNode } from 'react';
import { useTr } from '@/lib/shop';

/**
 * Sign-in, register, password and 2FA pages inside the store (approved
 * mockup: white card on the grey page). Pages pass their title and
 * description in English; Arabic comes from lang/ar/ui.php.
 */
export default function StoreAuthLayout({ children, title, description }: { children: ReactNode; title?: string; description?: string }) {
    const tr = useTr();

    return (
        <section className="flex justify-center px-4 pt-16 pb-24">
            <div className="flex w-full max-w-[440px] flex-col gap-6 bg-white px-6 py-10 sm:px-9">
                {title && (
                    <div className="flex flex-col gap-2 text-center">
                        <h1 className="store-caps text-lg">{tr(title)}</h1>
                        {description && <p className="text-sm text-subtle">{tr(description)}</p>}
                    </div>
                )}
                {children}
            </div>
        </section>
    );
}
