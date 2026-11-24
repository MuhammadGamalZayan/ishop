import { router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { ProviderIcon, providerName } from '@/components/shop/social-buttons';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/shop';

export type ConnectedAccount = { provider: 'google' | 'github'; email: string | null; connected: boolean };

/** Settings → Profile: link or unlink Google / GitHub (only providers with keys set). */
export function ConnectedAccounts({ accounts }: { accounts: ConnectedAccount[] }) {
    const t = useT();
    const [busy, setBusy] = useState<string | null>(null);

    if (accounts.length === 0) return null;

    const disconnect = (provider: string) => {
        setBusy(provider);
        router.delete(`/settings/social/${provider}`, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    return (
        <div className="space-y-6">
            <Heading variant="small" title={t('social.title')} description={t('social.text')} />
            <ul className="divide-y rounded-lg border">
                {accounts.map((a) => (
                    <li key={a.provider} className="flex flex-wrap items-center gap-3 p-4">
                        <ProviderIcon provider={a.provider} className="size-5" />
                        <div className="flex min-w-0 flex-1 flex-col">
                            <span className="text-sm font-medium">{providerName(a.provider)}</span>
                            <span className="truncate text-xs text-muted-foreground" dir="auto">
                                {a.connected ? t('social.connected_as', { email: a.email ?? '' }) : t('social.not_connected')}
                            </span>
                        </div>
                        {a.connected ? (
                            <Button variant="secondary" size="sm" disabled={busy === a.provider} onClick={() => disconnect(a.provider)}>
                                {t('social.disconnect')}
                            </Button>
                        ) : (
                            // Full page load: the provider's consent page is on its own site.
                            <Button asChild variant="outline" size="sm">
                                <a href={`/auth/${a.provider}/redirect`}>{t('social.connect')}</a>
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
