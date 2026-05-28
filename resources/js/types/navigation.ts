import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

export type AucMenuItem = {
    id: number;
    code: string;
    title: string;
    href: string | null;
    icon: string | null;
    children: AucMenuItem[];
};

export type AucApplication = {
    id: number;
    code: string;
    name: string;
    base_url: string;
    icon: string | null;
    authorize_url: string;
};

export type AucTenant = {
    id: number;
    code: string;
    name: string;
    status: string;
};
