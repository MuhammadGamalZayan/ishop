import { useShop, useT } from '@/lib/shop';

const NAMES = { google: 'Google', github: 'GitHub' } as const;

export function ProviderIcon({ provider, className = 'size-4.5' }: { provider: 'google' | 'github'; className?: string }) {
    if (provider === 'google') {
        return (
            <svg viewBox="0 0 48 48" className={className} aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.6 2.4 30.2 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.8 6C12.4 13.7 17.7 9.5 24 9.5z" />
                <path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.2 5.3-4.6 6.9l7.3 5.7c4.3-4 7-9.9 7-17.1z" />
                <path fill="#FBBC05" d="M10.5 28.7c-.5-1.4-.8-3-.8-4.7s.3-3.2.8-4.7l-7.8-6C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.8-6z" />
                <path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.3-5.7c-2 1.4-4.7 2.3-8.6 2.3-6.3 0-11.6-4.2-13.5-9.9l-7.8 6C6.6 42.6 14.6 48 24 48z" />
            </svg>
        );
    }

    return (
        <svg viewBox="0 0 16 16" className={className} fill="currentColor" aria-hidden="true">
            <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38v-1.34c-2.23.48-2.7-1.07-2.7-1.07-.36-.92-.89-1.17-.89-1.17-.73-.5.06-.49.06-.49.8.06 1.23.83 1.23.83.72 1.22 1.87.87 2.33.66.07-.52.28-.87.5-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.6 7.6 0 0 1 4 0c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48v2.2c0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z" />
        </svg>
    );
}

export function providerName(provider: 'google' | 'github'): string {
    return NAMES[provider];
}

/**
 * "Continue with Google / GitHub" above the sign-in and register forms.
 * Plain links (full page load): the provider's sign-in page is on its own site.
 * Renders nothing until a provider's keys are set in .env.
 */
export function SocialButtons() {
    const { socialProviders } = useShop();
    const t = useT();

    if (!socialProviders?.length) return null;

    return (
        <div className="flex flex-col gap-2.5">
            {socialProviders.map((provider) => (
                <a
                    key={provider}
                    href={`/auth/${provider}/redirect`}
                    className="flex h-12 items-center justify-center gap-2.5 border border-ink bg-white text-sm transition hover:bg-mist"
                >
                    <ProviderIcon provider={provider} />
                    {t('social.continue_with', { provider: NAMES[provider] })}
                </a>
            ))}
            <div className="my-3 flex items-center gap-3 text-xs text-[#8a8a8a] uppercase ltr:tracking-[0.14em]" role="separator">
                <span className="h-px flex-1 bg-[#d0d0d0]" />
                {t('social.or')}
                <span className="h-px flex-1 bg-[#d0d0d0]" />
            </div>
        </div>
    );
}
