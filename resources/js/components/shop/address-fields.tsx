import { useT } from '@/lib/shop';
import { money, useShop } from '@/lib/shop';

export type Governorate = { id: number; name: string; areas: { id: number; name: string; price: number }[] };

export type AddressValues = {
    label?: string;
    first_name: string;
    last_name: string;
    phone: string;
    governorate_id: number | '';
    shipping_area_id: number | '';
    street: string;
    building: string;
    floor: string;
    apartment: string;
    landmark: string;
};

export const emptyAddress: AddressValues = {
    label: '',
    first_name: '',
    last_name: '',
    phone: '',
    governorate_id: '',
    shipping_area_id: '',
    street: '',
    building: '',
    floor: '',
    apartment: '',
    landmark: '',
};

const input = 'h-12 w-full border border-[#d9d9d9] bg-white px-3.5 text-[15px] text-ink';

/**
 * Delivery address fields (checkout and My account): governorate first,
 * then only the areas we deliver to there, each with its price.
 */
export function AddressFields({
    values,
    onChange,
    governorates,
    errors = {},
    errorPrefix = '',
    withLabel = false,
}: {
    values: AddressValues;
    onChange: (values: AddressValues) => void;
    governorates: Governorate[];
    errors?: Record<string, string>;
    errorPrefix?: string;
    withLabel?: boolean;
}) {
    const t = useT();
    const { locale } = useShop();
    const areas = governorates.find((g) => g.id === values.governorate_id)?.areas ?? [];
    const set = (key: keyof AddressValues, value: string | number) => onChange({ ...values, [key]: value });
    const error = (key: string) => errors[errorPrefix + key];

    const field = (key: keyof AddressValues, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
        <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
            {t(`address.${key}`)}
            <input value={String(values[key] ?? '')} onChange={(e) => set(key, e.target.value)} aria-invalid={!!error(key)} className={input} {...props} />
            {error(key) && <span className="text-xs text-alert">{error(key)}</span>}
        </label>
    );

    return (
        <div className="flex flex-col gap-3">
            {withLabel && field('label', { maxLength: 50 })}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {field('first_name', { autoComplete: 'given-name', required: true, maxLength: 100 })}
                {field('last_name', { autoComplete: 'family-name', required: true, maxLength: 100 })}
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                    {t('address.governorate')}
                    <select
                        value={values.governorate_id}
                        onChange={(e) => onChange({ ...values, governorate_id: e.target.value ? Number(e.target.value) : '', shipping_area_id: '' })}
                        className={input}
                        required
                    >
                        <option value="">{t('checkout.choose_governorate')}</option>
                        {governorates.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                    {t('address.shipping_area_id')}
                    <select
                        value={values.shipping_area_id}
                        onChange={(e) => set('shipping_area_id', e.target.value ? Number(e.target.value) : '')}
                        disabled={!values.governorate_id}
                        aria-invalid={!!error('shipping_area_id')}
                        className={`${input} disabled:bg-[#f2f2f2]`}
                        required
                    >
                        <option value="">{values.governorate_id ? t('checkout.choose_area') : t('checkout.area_first')}</option>
                        {areas.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name} — {a.price === 0 ? t('checkout.free') : money(a.price, locale)}
                            </option>
                        ))}
                    </select>
                    {error('shipping_area_id') && <span className="text-xs text-alert">{error('shipping_area_id')}</span>}
                </label>
            </div>
            {field('street', { autoComplete: 'address-line1', required: true, maxLength: 255 })}
            <div className="grid grid-cols-3 gap-3">
                {field('building', { required: true, maxLength: 50 })}
                {field('floor', { maxLength: 20 })}
                {field('apartment', { maxLength: 20 })}
            </div>
            {field('landmark', { maxLength: 255 })}
            <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                {t('address.phone')}
                <input
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                    dir="ltr"
                    placeholder="01X XXXX XXXX"
                    value={values.phone}
                    onChange={(e) => set('phone', e.target.value)}
                    aria-invalid={!!error('phone')}
                    className={`${input} text-start`}
                    required
                    maxLength={20}
                />
                <span className="text-xs">{t('checkout.phone_help')}</span>
                {error('phone') && <span className="text-xs text-alert">{error('phone')}</span>}
            </label>
        </div>
    );
}

/** The fields the server expects (governorate is worked out from the area). */
export function addressPayload(values: AddressValues): Record<string, string | number | null> {
    const { governorate_id: _ignored, ...rest } = values;
    void _ignored;

    return Object.fromEntries(Object.entries(rest).map(([k, v]) => [k, v === '' ? null : v]));
}

export function addressComplete(v: AddressValues): boolean {
    return !!(v.first_name && v.last_name && v.phone && v.shipping_area_id && v.street && v.building);
}
