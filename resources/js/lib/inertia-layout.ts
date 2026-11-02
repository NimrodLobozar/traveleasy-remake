import type { ComponentType, ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import ShellLayout from '@/layouts/shell-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

type Layout = ComponentType<{ children: ReactNode }>;

/** Layout per page name, shared by the client (app.tsx) and server (ssr.tsx) entries. */
export function layoutFor(name: string): Layout | Layout[] | null {
    switch (true) {
        case name === 'home':
            return ShellLayout;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}
