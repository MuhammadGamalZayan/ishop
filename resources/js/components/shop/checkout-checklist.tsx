import { CheckCircle2, Circle } from 'lucide-react';
import { useT } from '@/lib/shop';

export type ChecklistItem = { key: string; label: string; done: boolean };

/**
 * "What's left before you can continue" at the top of the checkout. Each
 * line turns green with a tick as soon as it's done; the counter says how
 * far along the customer is. Mirrors the rules that enable Continue.
 */
export function CheckoutChecklist({ items }: { items: ChecklistItem[] }) {
    const t = useT();
    const done = items.filter((i) => i.done).length;
    const complete = done === items.length;

    return (
        <section
            aria-labelledby="checklist-title"
            className={`flex flex-col gap-3 rounded-lg border px-4.5 py-4 transition-colors ${complete ? 'border-[#9bd3ab] bg-[#eef8f1]' : 'border-[#d9d9d9] bg-[#fafafa]'}`}
        >
            <div className="flex items-center justify-between gap-3">
                <h2 id="checklist-title" className="text-[15px] font-medium">
                    {complete ? t('checkout.checklist_done') : t('checkout.checklist_title')}
                </h2>
                <span className={`text-[13px] ${complete ? 'text-[#1e7a3e]' : 'text-subtle'}`} aria-live="polite">
                    {t('checkout.checklist_progress', { done, total: items.length })}
                </span>
            </div>
            <ul className="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                {items.map((item) => (
                    <li key={item.key} className={`flex items-center gap-2 text-[13px] ${item.done ? 'text-[#1e7a3e]' : 'text-[#5f5f5f]'}`}>
                        {item.done ? (
                            <CheckCircle2 className="size-4.5 flex-none text-[#1e9e4a]" aria-hidden="true" />
                        ) : (
                            <Circle className="size-4.5 flex-none text-[#b5b5b5]" aria-hidden="true" />
                        )}
                        <span>
                            {item.label}
                            <span className="sr-only"> — {item.done ? t('checkout.checklist_ok') : t('checkout.checklist_todo')}</span>
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
