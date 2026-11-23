import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ProductCard } from '@/components/shop/product-card';
import { useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

type Tab = { name: string; url: string; products: ProductCardData[] };

export default function Home({ tabs, heroLinks }: { tabs: Tab[]; heroLinks: { name: string; url: string }[] }) {
    const t = useT();
    const [active, setActive] = useState(0);
    const tab = tabs[active];

    return (
        <>
            <Head title={t('home.title')} />

            <section className="relative flex h-[clamp(420px,70vh,640px)] items-center justify-center bg-[#8f8579] px-6 text-center">
                <div className="flex flex-col items-center gap-5.5 text-white">
                    <p className="store-caps text-[13px]">{t('home.hero_kicker')}</p>
                    <h1 className="text-[clamp(30px,5vw,46px)] font-light uppercase ltr:tracking-[0.2em]">
                        {t('home.hero_title')}
                    </h1>
                    <div className="flex flex-wrap justify-center gap-4">
                        {heroLinks.map((link, i) => (
                            <Link
                                key={link.url}
                                href={link.url}
                                className={`store-caps inline-flex h-11.5 min-w-[150px] items-center justify-center px-7 text-[13px] ${i === 0 ? 'bg-white text-ink' : 'bg-ink text-white'}`}
                            >
                                {link.name}
                            </Link>
                        ))}
                    </div>
                </div>
            </section>

            <section className="px-4 pt-22 pb-24 sm:px-10">
                <div className="mx-auto flex max-w-[1280px] flex-col items-center gap-4.5">
                    <p className="store-caps text-xs text-[#4a4a4a]">{t('home.best_kicker')}</p>

                    {tabs.length === 0 ? (
                        <p className="py-16 text-subtle">{t('home.empty')}</p>
                    ) : (
                        <>
                            <div role="tablist" className="flex flex-wrap justify-center gap-9">
                                {tabs.map((item, i) => (
                                    <button
                                        key={item.url}
                                        type="button"
                                        role="tab"
                                        aria-selected={i === active}
                                        onClick={() => setActive(i)}
                                        className={`store-caps border-b pb-1.5 text-[clamp(22px,3vw,30px)] font-light ${i === active ? 'border-ink' : 'border-transparent text-subtle'}`}
                                    >
                                        {item.name}
                                    </button>
                                ))}
                            </div>
                            <div role="tabpanel" className="mt-10 grid w-full grid-cols-[repeat(auto-fill,minmax(min(230px,42vw),1fr))] gap-x-7 gap-y-10">
                                {tab.products.map((product) => (
                                    <ProductCard key={product.id} product={product} />
                                ))}
                            </div>
                            <Link href={tab.url} className="store-caps mt-9 text-[13px] underline underline-offset-[6px]">
                                {t('home.view_all')}
                            </Link>
                        </>
                    )}
                </div>
            </section>

            {heroLinks.length > 0 && (
                <section className="grid grid-cols-[repeat(auto-fit,minmax(min(320px,100%),1fr))]">
                    {heroLinks.map((link, i) => (
                        <Link key={link.url} href={link.url} className={`flex h-[clamp(320px,45vw,520px)] items-end p-10 ${i === 0 ? 'bg-[#8f8a7c]' : 'bg-[#6f6a66]'}`}>
                            <span className="store-caps text-[28px] font-light text-white">{link.name}</span>
                        </Link>
                    ))}
                </section>
            )}
        </>
    );
}
