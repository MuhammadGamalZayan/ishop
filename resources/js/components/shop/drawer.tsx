import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useShop, useT } from '@/lib/shop';
import { cn } from '@/lib/utils';

/**
 * Side drawer (cart, filters) on Radix Dialog: focus is trapped, Esc and the
 * backdrop close it, the page behind can't scroll. It opens from the end
 * side, so it is on the right in English and on the left in Arabic.
 */
export function Drawer({
    open,
    onOpenChange,
    title,
    children,
    footer,
    className,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
}) {
    const { dir } = useShop();
    const t = useT();
    const fromLeft = dir === 'rtl';

    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/45" />
                <Dialog.Content
                    dir={dir}
                    aria-describedby={undefined}
                    className={cn(
                        'store data-[state=open]:animate-in data-[state=closed]:animate-out fixed inset-y-0 z-50 flex w-[460px] max-w-full flex-col bg-white shadow-xl duration-300',
                        fromLeft
                            ? 'left-0 data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left'
                            : 'right-0 data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right',
                        className,
                    )}
                >
                    <div className="flex h-[72px] flex-none items-center justify-between border-b border-line px-7">
                        <Dialog.Title className="store-caps text-[15px] font-normal">{title}</Dialog.Title>
                        <Dialog.Close aria-label={t('nav.close')} className="-me-3 flex size-11 items-center justify-center">
                            <X className="size-[18px]" strokeWidth={1.3} />
                        </Dialog.Close>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
                    {footer && <div className="flex-none border-t border-line px-7 pt-5 pb-6">{footer}</div>}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
