import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import { Drawer } from '@/components/shop/drawer';
import { ProductCard } from '@/components/shop/product-card';
import { useShop, useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

type Filters = { type: string[]; colour: string[]; in_stock: boolean; min: number | null; max: number | null; sort: string; q: string };
type Facets = {
    types: { value: string; label: string; count: number }[];
    colours: { value: string; label: string; hex: string | null }[];
    price: { min: number; max: number };
};
type Props = {
    category: { name: string; description: string | null; image: string | null; slug: string; url: string; parent: { name: string; url: string } | null };
    products: ProductCardData[];
    pagination: { total: number; label: string; current: number; last: number; prev: string | null; next: string | null };
    facets: Facets;
    filters: Filters;
};

const SORTS = ['featured', 'best', 'newest', 'price_asc', 'price_desc'];

/** Category page from the mockups: hero, toolbar, active filter chips, grid, filter drawer. */
export default function Category({ category, products, pagination, facets, filters }: Props) {
    const t = useT();
    const { locale } = useShop();
    const [density, setDensity] = useState<2 | 3 | 4>(4);
    const [drawer, setDrawer] = useState(false);
    const [draft, setDraft] = useState<Filters>(filters);

    /** Send filters as a GET (shareable URL); empty values are left out. */
    const apply = (next: Filters) => {
        const query: Record<string, string | number | string[]> = {};
        if (next.type.length) query.type = next.type;
        if (next.colour.length) query.colour = next.colour;
        if (next.in_stock) query.in_stock = 1;
        if (next.min !== null) query.min = next.min;
        if (next.max !== null) query.max = next.max;
        if (next.sort !== 'featured') query.sort = next.sort;
        if (next.q) query.q = next.q;
        router.get(category.url, query, { preserveState: false, preserveScroll: false });
    };

    const toggleIn = (list: string[], value: string) => (list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);
    const cleared: Filters = { type: [], colour: [], in_stock: false, min: null, max: null, sort: filters.sort, q: filters.q };

    const chips = [
        ...filters.type.map((v) => ({ label: facets.types.find((x) => x.value === v)?.label ?? v, next: { ...filters, type: filters.type.filter((x) => x !== v) } })),
        ...filters.colour.map((v) => ({ label: facets.colours.find((x) => x.value === v)?.label ?? v, next: { ...filters, colour: filters.colour.filter((x) => x !== v) } })),
        ...(filters.in_stock ? [{ label: t('category.in_stock'), next: { ...filters, in_stock: false } }] : []),
        ...(filters.min !== null || filters.max !== null ? [{ label: `${filters.min ?? 0} – ${filters.max ?? '∞'}`, next: { ...filters, min: null, max: null } }] : []),
    ];
    const activeCount = chips.length;
    const minWidth = { 2: 440, 3: 320, 4: 230 }[density];
    const title = filters.q ? t('category.search_for', { q: filters.q }) : category.name;

    return (
        <>
            <Head title={title}>{category.description && <meta name="description" content={category.description} />}</Head>

            <section className="relative flex min-h-[320px] items-center justify-center bg-[#7b746b] px-6 py-16 text-center">
                {category.image && <img src={category.image} alt="" className="absolute inset-0 h-full w-full object-cover" />}
                {category.image && <span className="absolute inset-0 bg-black/30" aria-hidden="true" />}
                <div className="relative flex max-w-[680px] flex-col gap-4.5 text-white">
                    <nav aria-label={t('category.breadcrumb')} className="store-caps text-xs">
                        <Link href={`/${locale}`}>{t('category.home')}</Link>
                        <span aria-hidden="true"> / </span>
                        {category.parent && (
                            <>
                                <Link href={category.parent.url}>{category.parent.name}</Link>
                                <span aria-hidden="true"> / </span>
                            </>
                        )}
                        <span aria-current="page">{category.name}</span>
                    </nav>
                    <h1 className="text-[clamp(28px,4vw,40px)] font-light uppercase ltr:tracking-[0.2em]">{title}</h1>
                    {category.description && !filters.q && <p className="text-base leading-relaxed">{category.description}</p>}
                </div>
            </section>

            <div className="flex flex-wrap items-stretch border-b border-line">
                <div role="group" aria-label={t('category.grid')} className="hidden items-center gap-1 border-e border-line px-5.5 py-1.5 md:flex">
                    {([2, 3, 4] as const).map((n) => (
                        <button key={n} type="button" onClick={() => setDensity(n)} aria-pressed={density === n} aria-label={t('category.per_row', { count: n })} className="flex size-11 items-center justify-center">
                            <span className="grid gap-[1.5px]" style={{ gridTemplateColumns: `repeat(${n}, 1fr)` }} aria-hidden="true">
                                {Array.from({ length: n * n }).map((_, i) => (
                                    <span key={i} className={density === n ? 'bg-ink' : 'bg-[#9a9a9a]'} style={{ width: 16 / n, height: 16 / n }} />
                                ))}
                            </span>
                        </button>
                    ))}
                </div>
                <p className="store-caps flex min-h-14 flex-[1_1_160px] items-center justify-center px-5.5 text-[13px] text-[#4a4a4a]">{pagination.label}</p>
                <label className="store-caps flex items-center gap-2.5 border-s border-line px-5.5 text-[13px] text-[#4a4a4a]">
                    <span className="hidden sm:inline">{t('category.sort_by')}</span>
                    <select value={filters.sort} onChange={(e) => apply({ ...filters, sort: e.target.value })} className="h-11 cursor-pointer bg-transparent text-[13px] text-ink">
                        {SORTS.map((s) => (
                            <option key={s} value={s}>
                                {t(`category.sorts.${s}`)}
                            </option>
                        ))}
                    </select>
                </label>
                <button
                    type="button"
                    onClick={() => {
                        setDraft(filters);
                        setDrawer(true);
                    }}
                    className="store-caps flex min-h-14 items-center gap-2.5 border-s border-line px-7 text-[13px]"
                >
                    <SlidersHorizontal className="size-4" strokeWidth={1.4} aria-hidden="true" />
                    {t('category.filter')}
                    {activeCount > 0 && ` (${activeCount})`}
                </button>
            </div>

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-2.5 px-4 pt-4.5 sm:px-10">
                    {chips.map((chip) => (
                        <button key={chip.label} type="button" onClick={() => apply(chip.next)} aria-label={t('category.remove_filter', { name: chip.label })} className="inline-flex h-9 items-center gap-2 border border-[#c9c9c9] bg-white px-3 text-[13px]">
                            {chip.label}
                            <X className="size-2.5" strokeWidth={2.4} aria-hidden="true" />
                        </button>
                    ))}
                    <button type="button" onClick={() => apply(cleared)} className="text-[13px] underline underline-offset-4">
                        {t('category.clear_all')}
                    </button>
                </div>
            )}

            <section className="px-4 pt-12 pb-24 sm:px-10">
                {products.length === 0 ? (
                    <p className="my-10 text-center text-[15px] text-[#4a4a4a]">{t('category.no_results')}</p>
                ) : (
                    <div className="grid gap-x-7 gap-y-12" style={{ gridTemplateColumns: `repeat(auto-fill, minmax(min(${minWidth}px, 42vw), 1fr))` }}>
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}

                {pagination.last > 1 && (
                    <nav aria-label={t('category.page', { current: pagination.current, last: pagination.last })} className="mt-14 flex items-center justify-center gap-6 text-sm">
                        {pagination.prev ? <Link href={pagination.prev} className="underline underline-offset-4">{t('category.previous')}</Link> : <span className="text-subtle">{t('category.previous')}</span>}
                        <span>{t('category.page', { current: pagination.current, last: pagination.last })}</span>
                        {pagination.next ? <Link href={pagination.next} className="underline underline-offset-4">{t('category.next')}</Link> : <span className="text-subtle">{t('category.next')}</span>}
                    </nav>
                )}
            </section>

            <Drawer
                open={drawer}
                onOpenChange={setDrawer}
                title={t('category.filters')}
                footer={
                    <div className="flex gap-3">
                        <button type="button" onClick={() => setDraft(cleared)} className="store-caps h-13 border border-ink px-5 text-[13px]">
                            {t('category.clear')}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setDrawer(false);
                                apply(draft);
                            }}
                            className="store-caps h-13 flex-1 bg-ink text-[13px] text-white"
                        >
                            {t('category.view_results')}
                        </button>
                    </div>
                }
            >
                <div className="px-7">
                    {facets.types.length > 0 && (
                        <FilterSection title={t('category.type')} defaultOpen>
                            {facets.types.map((type) => (
                                <label key={type.value} className="flex min-h-10 cursor-pointer items-center gap-3 text-[15px] text-[#4a4a4a]">
                                    <input type="checkbox" checked={draft.type.includes(type.value)} onChange={() => setDraft({ ...draft, type: toggleIn(draft.type, type.value) })} className="size-4.5 accent-ink" />
                                    {type.label} ({type.count})
                                </label>
                            ))}
                        </FilterSection>
                    )}
                    {facets.colours.length > 0 && (
                        <FilterSection title={t('category.colour')} defaultOpen>
                            <div className="flex flex-wrap gap-2">
                                {facets.colours.map((colour) => {
                                    const on = draft.colour.includes(colour.value);

                                    return (
                                        <button
                                            key={colour.value}
                                            type="button"
                                            onClick={() => setDraft({ ...draft, colour: toggleIn(draft.colour, colour.value) })}
                                            aria-pressed={on}
                                            aria-label={colour.label}
                                            title={colour.label}
                                            className={`size-11 border p-[3px] ${on ? 'border-ink' : 'border-transparent'}`}
                                        >
                                            <span className="block h-full w-full outline outline-1 outline-black/10" style={{ background: colour.hex ?? '#ccc' }} />
                                        </button>
                                    );
                                })}
                            </div>
                        </FilterSection>
                    )}
                    <FilterSection title={t('category.availability')}>
                        <label className="flex min-h-10 cursor-pointer items-center gap-3 text-[15px] text-[#4a4a4a]">
                            <input type="checkbox" checked={draft.in_stock} onChange={() => setDraft({ ...draft, in_stock: !draft.in_stock })} className="size-4.5 accent-ink" />
                            {t('category.in_stock')}
                        </label>
                    </FilterSection>
                    <FilterSection title={t('category.price')}>
                        <div className="grid grid-cols-2 gap-3">
                            {(['min', 'max'] as const).map((key) => (
                                <label key={key} className="flex flex-col gap-1.5 text-xs text-[#4a4a4a]">
                                    {t(key === 'min' ? 'category.from' : 'category.to')}
                                    <input
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        placeholder={String(key === 'min' ? facets.price.min : facets.price.max)}
                                        value={draft[key] ?? ''}
                                        onChange={(e) => setDraft({ ...draft, [key]: e.target.value === '' ? null : Math.max(0, parseInt(e.target.value, 10) || 0) })}
                                        className="h-11 border border-[#c9c9c9] px-3 text-sm"
                                    />
                                </label>
                            ))}
                        </div>
                    </FilterSection>
                </div>
            </Drawer>
        </>
    );
}

function FilterSection({ title, children, defaultOpen = false }: { title: string; children: React.ReactNode; defaultOpen?: boolean }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <section className="border-b border-[#e2e2e2]">
            <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="store-caps flex h-15 w-full items-center justify-between text-[13px]">
                {title}
                <ChevronDown className={`size-3 transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
            </button>
            {open && <div className="pb-5">{children}</div>}
        </section>
    );
}
