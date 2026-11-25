import { Head, useForm } from '@inertiajs/react';
import { useT } from '@/lib/shop';

type Props = { page: { title: string; body: string | null; template: 'default' | 'contact'; meta_description: string | null } };

/**
 * A content page from the admin panel (Our story, FAQ…). The body is HTML
 * from Filament's rich editor, which only produces a safe subset of tags,
 * written by staff. "contact" pages add the contact form under the text.
 */
export default function ContentPage({ page }: Props) {
    return (
        <>
            <Head title={page.title}>{page.meta_description && <meta name="description" content={page.meta_description} />}</Head>
            <article className="px-4 py-16 sm:px-10 lg:py-20">
                <div className="mx-auto flex max-w-[760px] flex-col gap-8">
                    <h1 className="text-center text-[clamp(26px,3.4vw,34px)] font-light uppercase ltr:tracking-[0.16em]">{page.title}</h1>
                    {page.body && (
                        <div
                            className="text-[15px] leading-relaxed text-[#3a3a3a] [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:border-s-2 [&_blockquote]:ps-4 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-medium [&_h2]:text-ink [&_h3]:mt-4 [&_h3]:font-medium [&_li]:ms-5 [&_ol]:list-decimal [&_p]:mt-3 [&_ul]:list-disc"
                            dangerouslySetInnerHTML={{ __html: page.body }}
                        />
                    )}
                    {page.template === 'contact' && <ContactForm />}
                </div>
            </article>
        </>
    );
}

function ContactForm() {
    const t = useT();
    const form = useForm({ name: '', email: '', phone: '', message: '' });
    const field = 'h-12 w-full border border-[#d9d9d9] bg-white px-3.5 text-[15px]';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/contact', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const error = (key: keyof typeof form.data) => form.errors[key] && <span className="text-xs text-alert">{form.errors[key]}</span>;

    return (
        <form onSubmit={submit} className="mt-4 flex flex-col gap-4 bg-white p-6 sm:p-8">
            <h2 className="store-caps text-sm">{t('contact.title')}</h2>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                    <span>
                        {t('contact.name')} <span className="text-alert" aria-hidden="true">*</span>
                    </span>
                    <input required maxLength={120} autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={field} />
                    {error('name')}
                </label>
                <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                    <span>
                        {t('contact.email')} <span className="text-alert" aria-hidden="true">*</span>
                    </span>
                    <input type="email" required autoComplete="email" dir="ltr" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className={`${field} text-start`} />
                    {error('email')}
                </label>
            </div>
            <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                {t('contact.phone')}
                <input type="tel" maxLength={30} autoComplete="tel" dir="ltr" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className={`${field} text-start`} />
                {error('phone')}
            </label>
            <label className="flex flex-col gap-1.5 text-[13px] text-subtle">
                <span>
                    {t('contact.message')} <span className="text-alert" aria-hidden="true">*</span>
                </span>
                <textarea required minLength={10} maxLength={3000} rows={5} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} className="w-full border border-[#d9d9d9] bg-white p-3.5 text-[15px]" />
                {error('message')}
            </label>
            <button type="submit" disabled={form.processing} className="store-caps h-12 self-start bg-ink px-8 text-[13px] text-white disabled:opacity-60">
                {t('contact.send')}
            </button>
        </form>
    );
}
