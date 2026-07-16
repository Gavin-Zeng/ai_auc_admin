export type User = {
    id: number;
    account: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    is_platform_admin?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Tenant = {
    id: number;
    code: string;
    name: string;
    status: string;
};

export type Auth = {
    user: User;
    tenant: Tenant | null;
    tenants: Tenant[];
    membership: {
        status: string;
        is_owner: boolean;
        permission_version: number;
    } | null;
    identity: {
        is_platform_admin: boolean;
        is_company_owner: boolean;
    };
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
