import type { SharedUserData } from '@/types/generated';

/**
 * The shared `auth` Inertia prop. `user` is generated from App\Data\SharedUserData
 * (`npm run generate`) and is `null` for guests.
 */
export type Auth = {
    user: SharedUserData | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
