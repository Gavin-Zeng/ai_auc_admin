<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AppWindow,
    BarChart3,
    Building2,
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
import { index as applicationsIndex } from '@/routes/applications';
import { index as gamesIndex } from '@/routes/games';
import { index as menusIndex } from '@/routes/menus';
import { index as rolesIndex } from '@/routes/roles';
import { index as tenantsIndex } from '@/routes/tenants';
import { index as usersIndex } from '@/routes/users';
import type { AucMenuItem, NavItem } from '@/types';

const iconMap = {
    'app-window': AppWindow,
    applications: AppWindow,
    'bar-chart-3': BarChart3,
    'building-2': Building2,
    dashboard: LayoutDashboard,
    'layout-dashboard': LayoutDashboard,
    'list-tree': ListTree,
    'message-square': MessageSquare,
    menus: ListTree,
    package: Package,
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
const menus = computed(() => {
    const auc = page.props.auc as { menus?: AucMenuItem[] } | undefined;

    return auc?.menus ?? [];
});
const hasPlatformAccess = computed(
    () => page.props.auth.identity?.has_platform_access === true,
);
const hasConfiguredMenus = computed(() => menus.value.length > 0);
const isCompanyAdmin = computed(
    () => page.props.auth.identity?.is_company_owner === true,
);

const platformFallbackItems: Array<NavItem & { permission: string }> = [
    { title: '仪表盘', href: dashboard(), icon: LayoutDashboard, permission: 'dashboard.view' },
    { title: '公司管理', href: tenantsIndex(), icon: Users, permission: 'tenants.manage' },
    { title: '用户管理', href: usersIndex(), icon: UserCog, permission: 'users.manage' },
    { title: '角色管理', href: rolesIndex(), icon: ShieldCheck, permission: 'roles.manage' },
    { title: '菜单管理', href: menusIndex(), icon: ListTree, permission: 'menus.manage' },
    { title: '系统管理', href: applicationsIndex(), icon: AppWindow, permission: 'applications.manage' },
    { title: '游戏管理', href: gamesIndex(), icon: Package, permission: 'games.manage' },
];

const mainNavItems = computed<NavItem[]>(() => {
    if (hasPlatformAccess.value) {
        return platformFallbackItems;
    }

    if (!hasConfiguredMenus.value) {
        return [
            {
                title: '仪表盘',
                href: dashboard(),
                icon: LayoutDashboard,
            },
        ];
    }

    return [
        { title: '仪表盘', href: dashboard(), icon: Building2 },
        ...(isCompanyAdmin.value
            ? [
                  { title: '用户管理', href: usersIndex(), icon: UserCog },
                  { title: '角色管理', href: rolesIndex(), icon: ShieldCheck },
              ]
            : []),
        ...menus.value.map((menu) => ({
            title: menu.title,
            href: menu.href ?? dashboard(),
            icon: iconMap[menu.icon as keyof typeof iconMap] ?? iconMap.default,
        })),
    ];
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
            <div
                v-if="!hasConfiguredMenus && !hasPlatformAccess"
                class="mx-2 rounded-md border border-dashed border-sidebar-border/70 p-3 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden"
            >
                <p class="font-medium text-sidebar-foreground">
                    当前公司尚未配置菜单
                </p>
                <p class="mt-1">请联系管理员配置菜单。</p>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
