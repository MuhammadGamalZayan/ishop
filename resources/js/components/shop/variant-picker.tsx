import { useMemo, useState } from 'react';
import { useT } from '@/lib/shop';

export type OptionValue = { id: number; name: string; hex: string | null };
export type OptionAttribute = { id: number; name: string; type: 'color' | 'select'; values: OptionValue[] };
export type PickVariant = { id: number; sku: string; price: number; compare_at: number | null; values: number[]; in_stock: boolean; left: number | null };

/**
 * Option choice shared by the product page and the quick-add window:
 * starts on the default variant, knows which values are still in stock
 * given the other choices, and which variant the choice points to.
 */
export function useVariantPicker(attributes: OptionAttribute[], variants: PickVariant[], defaultVariantId: number) {
    const defaultVariant = variants.find((v) => v.id === defaultVariantId) ?? variants[0];
    const [selected, setSelected] = useState<Record<number, number | null>>(() =>
        Object.fromEntries(attributes.map((a) => [a.id, defaultVariant?.values.find((id) => a.values.some((v) => v.id === id)) ?? null])),
    );

    const variant = useMemo(
        () => (attributes.length === 0 ? defaultVariant : variants.find((v) => attributes.every((a) => selected[a.id] !== null && v.values.includes(selected[a.id]!)))),
        [selected, attributes, variants, defaultVariant],
    );

    /** Is there an in-stock variant with this value, given the other choices? */
    const available = (attributeId: number, valueId: number) =>
        variants.some((v) => v.in_stock && v.values.includes(valueId) && attributes.every((a) => a.id === attributeId || selected[a.id] === null || v.values.includes(selected[a.id]!)));

    const missing = attributes.find((a) => selected[a.id] === null);

    return {
        selected,
        pick: (attributeId: number, valueId: number | null) => setSelected((s) => ({ ...s, [attributeId]: valueId })),
        variant,
        shown: variant ?? defaultVariant,
        available,
        missing,
        ready: !!variant && variant.in_stock,
    };
}

type Picker = ReturnType<typeof useVariantPicker>;

/** Colour as swatches, everything else as dropdowns (agreed), plus the availability line. */
export function VariantOptions({ attributes, picker, onPick }: { attributes: OptionAttribute[]; picker: Picker; onPick?: () => void }) {
    const t = useT();
    const { selected, variant, missing, available } = picker;

    const pick = (attributeId: number, valueId: number | null) => {
        picker.pick(attributeId, valueId);
        onPick?.();
    };

    const status = !variant
        ? { text: missing ? t('pdp.pick_first', { option: missing.name }) : t('pdp.unavailable'), colour: missing ? '#9a9a9a' : '#b3261e' }
        : !variant.in_stock
          ? { text: t('pdp.unavailable'), colour: '#b3261e' }
          : variant.left !== null
            ? { text: t('pdp.left', { count: variant.left }), colour: '#b26a00' }
            : { text: t('pdp.in_stock'), colour: '#2e7d32' };

    return (
        <>
            {attributes.map((attribute) => {
                const value = selected[attribute.id];
                const valueName = attribute.values.find((v) => v.id === value)?.name;

                return attribute.type === 'color' ? (
                    <fieldset key={attribute.id} className="flex flex-col gap-3">
                        <legend className="mb-3 text-sm">
                            <span className="text-subtle">{attribute.name}:</span> {valueName}
                        </legend>
                        <div className="flex flex-wrap gap-2.5">
                            {attribute.values.map((v) => {
                                const on = v.id === value;
                                const ok = available(attribute.id, v.id);
                                const label = ok ? v.name : t('pdp.sold_out_option', { name: v.name });

                                return (
                                    <button
                                        key={v.id}
                                        type="button"
                                        onClick={() => pick(attribute.id, v.id)}
                                        aria-pressed={on}
                                        aria-label={label}
                                        title={label}
                                        className={`relative size-11 border p-[3px] ${on ? 'border-ink' : 'border-transparent'}`}
                                    >
                                        <span className={`block h-full w-full outline outline-1 outline-black/10 ${ok ? '' : 'opacity-40'}`} style={{ background: v.hex ?? '#ccc' }} />
                                        {!ok && <span aria-hidden="true" className="absolute inset-0 m-auto h-px w-[130%] -translate-x-[12%] rotate-45 bg-ink" />}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                ) : (
                    <label key={attribute.id} className="flex flex-col gap-2 text-sm">
                        <span className="text-subtle">{attribute.name}</span>
                        <select value={value ?? ''} onChange={(e) => pick(attribute.id, e.target.value ? Number(e.target.value) : null)} className="h-12.5 border border-[#c9c9c9] bg-white px-3.5 text-[15px]">
                            <option value="">{t('pdp.choose')}</option>
                            {attribute.values.map((v) => {
                                const ok = available(attribute.id, v.id);

                                return (
                                    <option key={v.id} value={v.id} disabled={!ok}>
                                        {ok ? v.name : t('pdp.sold_out_option', { name: v.name })}
                                    </option>
                                );
                            })}
                        </select>
                    </label>
                );
            })}

            <p role="status" className="flex items-center gap-2 text-[13px] text-[#3a3a3a]">
                <span aria-hidden="true" className="size-2 rounded-full" style={{ background: status.colour }} />
                {status.text}
            </p>
        </>
    );
}
