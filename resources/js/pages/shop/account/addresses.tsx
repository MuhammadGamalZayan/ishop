import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AccountShell } from '@/components/shop/account-shell';
import { AddressFields, addressPayload, emptyAddress } from '@/components/shop/address-fields';
import type { AddressValues, Governorate } from '@/components/shop/address-fields';
import { useT } from '@/lib/shop';

type Address = { id: number; label: string | null; is_default: boolean; deliverable: boolean; area: string; fields: Omit<AddressValues, 'floor' | 'apartment' | 'landmark' | 'label'> & { label: string | null; floor: string | null; apartment: string | null; landmark: string | null } };

export default function Addresses({ addresses, governorates }: { addresses: Address[]; governorates: Governorate[] }) {
    const t = useT();
    const errors = usePage().props.errors as Record<string, string>;
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const [values, setValues] = useState<AddressValues>(emptyAddress);
    const [saving, setSaving] = useState(false);

    const open = (address?: Address) => {
        setEditing(address ? address.id : 'new');
        setValues(
            address
                ? { ...emptyAddress, ...Object.fromEntries(Object.entries(address.fields).map(([k, v]) => [k, v ?? ''])) } as AddressValues
                : emptyAddress,
        );
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        const options = { preserveScroll: true, onSuccess: () => setEditing(null), onFinish: () => setSaving(false) };

        if (editing === 'new') {
            router.post('/account/addresses', addressPayload(values), options);
        } else {
            router.put(`/account/addresses/${editing}`, addressPayload(values), options);
        }
    };

    const form = (
        <form onSubmit={save} className="flex flex-col gap-4 bg-white p-6">
            <h2 className="text-base font-medium">{editing === 'new' ? t('account.add_address') : t('account.edit')}</h2>
            <AddressFields values={values} onChange={setValues} governorates={governorates} errors={errors} withLabel />
            <div className="flex gap-2.5">
                <button type="submit" disabled={saving} className="store-caps h-11.5 bg-ink px-5.5 text-[13px] text-white disabled:opacity-60">
                    {t('account.save')}
                </button>
                <button type="button" onClick={() => setEditing(null)} className="store-caps h-11.5 border border-ink px-5.5 text-[13px]">
                    {t('account.cancel_edit')}
                </button>
            </div>
        </form>
    );

    return (
        <AccountShell>
            <Head title={t('account.addresses')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-[26px] font-light uppercase ltr:tracking-[0.16em]">{t('account.addresses')}</h1>
                {editing === null && (
                    <button type="button" onClick={() => open()} className="store-caps h-11 bg-ink px-5 text-[13px] text-white">
                        {t('account.add_address')}
                    </button>
                )}
            </div>

            {editing === 'new' && form}

            {addresses.length === 0 && editing === null && <p className="bg-white p-6 text-[15px] text-subtle">{t('account.no_addresses')}</p>}

            <div className="grid grid-cols-[repeat(auto-fill,minmax(min(280px,100%),1fr))] gap-4.5">
                {addresses.map((address) =>
                    editing === address.id ? (
                        <div key={address.id} className="col-span-full">{form}</div>
                    ) : (
                        <article key={address.id} className="flex flex-col gap-2.5 bg-white px-6 py-5.5 text-sm leading-relaxed">
                            <div className="flex items-center gap-2.5">
                                <span className="font-medium">{address.label || `${address.fields.first_name} ${address.fields.last_name}`}</span>
                                {address.is_default && <span className="store-caps bg-ink px-2 py-0.5 text-[11px] text-white">{t('account.default')}</span>}
                            </div>
                            <span className="text-[#3a3a3a]">
                                {address.fields.first_name} {address.fields.last_name} · {address.fields.street}, {address.fields.building} · {address.area} · <span dir="ltr">{address.fields.phone}</span>
                            </span>
                            {!address.deliverable && <span className="text-xs text-alert">{t('account.not_deliverable')}</span>}
                            <div className="mt-1.5 flex gap-4 text-[13px]">
                                <button type="button" onClick={() => open(address)} className="underline underline-offset-4">
                                    {t('account.edit')}
                                </button>
                                {!address.is_default && (
                                    <button type="button" onClick={() => router.post(`/account/addresses/${address.id}/default`, {}, { preserveScroll: true })} className="underline underline-offset-4">
                                        {t('account.make_default')}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => window.confirm(t('account.delete_confirm')) && router.delete(`/account/addresses/${address.id}`, { preserveScroll: true })}
                                    className="text-alert underline underline-offset-4"
                                >
                                    {t('account.delete')}
                                </button>
                            </div>
                        </article>
                    ),
                )}
            </div>
        </AccountShell>
    );
}
