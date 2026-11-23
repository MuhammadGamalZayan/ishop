import { createInertiaApp } from '@inertiajs/react';
import { AccountSettingsLayout } from '@/components/shop/account-shell';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import CheckoutLayout from '@/layouts/checkout-layout';
import StoreAuthLayout from '@/layouts/store-auth-layout';
import StoreLayout from '@/layouts/store-layout';

const appName = import.meta.env.VITE_APP_NAME || 'iShop';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name === 'shop/checkout':
                return CheckoutLayout;
            case name.startsWith('shop/'):
                return StoreLayout;
            // Sign in / register / password pages: inside the store design.
            case name.startsWith('auth/'):
                return [StoreLayout, StoreAuthLayout];
            // Profile and security: part of My account.
            case name.startsWith('settings/'):
                return [StoreLayout, AccountSettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#1a1a1a',
    },
});

// This will set light / dark mode on load...
initializeTheme();
