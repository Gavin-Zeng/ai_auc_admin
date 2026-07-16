<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AppWindow,
    BarChart3,
    Building2,
    ClipboardList,
    Gauge,
    KeyRound,
    LayoutDashboard,
    ListTree,
    MessageSquare,
    Package,
    ShoppingCart,
    ScrollText,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { AucMenuItem, NavItem } from '@/types';

const iconMap = {
    'app-window': AppWindow,
    applications: AppWindow,
    audit_logs: ClipboardList,
    'bar-chart-3': BarChart3,
    'building-2': Building2,
    'clipboard-list': ClipboardList,
    dashboard: LayoutDashboard,
    diagnostics: Gauge,
    'key-round': KeyRound,
    'layout-dashboard': LayoutDashboard,
    'list-tree': ListTree,
    'message-square': MessageSquare,
    menus: ListTree,
    package: Package,
    permissions: KeyRound,
    roles: ShieldCheck,
    settings: Settings,
    'shield-check': ShieldCheck,
    'shopping-cart': ShoppingCart,
    tenants: Users,
    'user-cog': UserCog,
    users: UserCog,
    wallet: Wallet,
    default: ScrollText,
};

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const auc = page.props.auc as { menus?: AucMenuItem[] } | undefined;
    const menus = auc?.menus ?? [];

    if (menus.length === 0) {
        return [
            {
                title: '仪表盘',
                href: dashboard(),
                icon: LayoutDashboard,
            },
        ];
    }

    return menus.map((menu) => ({
        title: menu.title,
        href: menu.href ?? dashboard(),
        icon: iconMap[menu.icon as keyof typeof iconMap] ?? iconMap.default,
    }));
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
