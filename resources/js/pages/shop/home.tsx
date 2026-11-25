import { Head } from '@inertiajs/react';
import { HeroSlider } from '@/components/shop/home/hero-slider';
import type { HeroSlide } from '@/components/shop/home/hero-slider';
import { BestSellers, CategoryTrio, ColourCompare, Looks, Marquee, ProductRow, SaleBanner, VideoBlock } from '@/components/shop/home/sections';
import type { Look, ProductTab } from '@/components/shop/home/sections';
import { useT } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';

type Props = {
    hero: HeroSlide[];
    bestSellers: { kicker: string; tabs: ProductTab[] } | null;
    newIn: { kicker: string; title: string; url: string; button: string; products: ProductCardData[] } | null;
    trio: { image: string | null; title: string; url: string }[];
    marquee: { text: string; url: string; image: string | null }[][];
    video: { video: string | null; poster: string | null; kicker: string; title: string } | null;
    looks: Look[];
    sale: { image: string | null; kicker: string; title: string; text: string; button: string; url: string; endsAt: string | null } | null;
    compare: { title: string; left: { image: string | null; label: string; url: string }; right: { image: string | null; label: string; url: string } } | null;
};

/**
 * Home page (approved mockup). Every section is set in the admin panel
 * (Home page) and is left out while it has no content.
 */
export default function Home({ hero, bestSellers, newIn, trio, marquee, video, looks, sale, compare }: Props) {
    const t = useT();
    const empty = hero.length === 0 && !bestSellers && !newIn;

    return (
        <>
            <Head title={t('home.title_seo')} />
            <h1 className="sr-only">iShop — {t('home.title_seo')}</h1>

            <HeroSlider slides={hero} />
            {bestSellers && <BestSellers data={bestSellers} />}
            {newIn && <ProductRow data={newIn} />}
            {trio.length === 3 && <CategoryTrio items={trio} />}
            {marquee.length > 0 && <Marquee rows={marquee} />}
            {video && <VideoBlock data={video} />}
            {looks.length > 0 && <Looks slides={looks} />}
            {sale && <SaleBanner data={sale} />}
            {compare && <ColourCompare data={compare} />}

            {empty && <p className="px-4 py-24 text-center text-subtle">{t('home.empty')}</p>}
        </>
    );
}
