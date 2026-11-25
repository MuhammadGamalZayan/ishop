import { useForm } from '@inertiajs/react';
import { FadeIn } from '@/components/shop/home/fade-in';
import { useShop, useT } from '@/lib/shop';

/** "Keep me updated" band above the footer on every store page (texts and photo from the admin panel). */
export function NewsletterBand() {
    const { newsletter } = useShop();
    const t = useT();
    const form = useForm({ email: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/newsletter', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <FadeIn as="section" className="relative overflow-hidden bg-[#2b2724] text-white">
            {newsletter.image && <img src={newsletter.image} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />}
            <div className="absolute inset-0 bg-black/45" />
            <div className="relative mx-auto flex min-h-[400px] max-w-[720px] flex-col items-center justify-center gap-4 px-6 py-16 text-center">
                <p className="store-caps text-xs">{newsletter.kicker}</p>
                <h2 className="text-[clamp(26px,3.4vw,36px)] font-light uppercase ltr:tracking-[0.16em]">{newsletter.title}</h2>
                <p className="text-[15px] leading-relaxed">{newsletter.text}</p>
                <form onSubmit={submit} className="mt-3 flex w-full flex-col gap-3 sm:flex-row" noValidate>
                    <label htmlFor="newsletter-email" className="sr-only">
                        {t('newsletter.email')}
                    </label>
                    <input
                        id="newsletter-email"
                        type="email"
                        required
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder={t('newsletter.email')}
                        aria-invalid={!!form.errors.email}
                        aria-describedby={form.errors.email ? 'newsletter-error' : undefined}
                        className="h-12 min-w-0 flex-1 border border-white/70 bg-transparent px-4 text-[15px] text-white placeholder:text-white/75 focus:border-white focus:outline-none"
                    />
                    <button type="submit" disabled={form.processing} className="store-caps h-12 bg-white px-8 text-[13px] text-ink transition-opacity hover:opacity-90 disabled:opacity-60">
                        {t('newsletter.subscribe')}
                    </button>
                </form>
                {form.errors.email && (
                    <p id="newsletter-error" role="alert" className="text-sm text-[#ffd2cf]">
                        {form.errors.email}
                    </p>
                )}
            </div>
        </FadeIn>
    );
}
