export type User = {
    id: number;
    account: string;
    name: string;
    email?: string;
    avatar?: string;
    is_company_admin?: boolean;
    is_platform_admin?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Tenant = {
    id: number;
    name: string;
    status: boolean;
};

export type Auth = {
    user: User;
    tenant: Tenant | null;
    tenants: Tenant[];
    membership: {
        status: boolean;
        is_company_admin: boolean;
    } | null;
    identity: {
        is_platform_admin: boolean;
        has_platform_access: boolean;
        platform_permissions: string[];
        is_company_owner: boolean;
    };
};
