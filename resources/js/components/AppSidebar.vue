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
import TenantSwitcher from '@/components/TenantSwitcher.vue';
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
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as menusIndex } from '@/routes/menus';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as rolesIndex } from '@/routes/roles';
import { index as tenantsIndex } from '@/routes/tenants';
import { index as usersIndex } from '@/routes/users';
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
const menus = computed(() => {
    const auc = page.props.auc as { menus?: AucMenuItem[] } | undefined;

    return auc?.menus ?? [];
});
const isPlatformAdmin = computed(
    () => page.props.auth.identity?.is_platform_admin === true,
);
const hasConfiguredMenus = computed(() => menus.value.length > 0);

const platformFallbackItems: NavItem[] = [
    { title: '仪表盘', href: dashboard(), icon: LayoutDashboard },
    { title: '公司管理', href: tenantsIndex(), icon: Users },
    { title: '用户管理', href: usersIndex(), icon: UserCog },
    { title: '角色管理', href: rolesIndex(), icon: ShieldCheck },
    { title: '权限管理', href: permissionsIndex(), icon: KeyRound },
    { title: '菜单管理', href: menusIndex(), icon: ListTree },
    { title: '系统管理', href: applicationsIndex(), icon: AppWindow },
    { title: '操作日志', href: auditLogsIndex(), icon: ClipboardList },
];

const mainNavItems = computed<NavItem[]>(() => {
    if (!hasConfiguredMenus.value) {
        if (isPlatformAdmin.value) {
            return platformFallbackItems;
        }

        return [
            {
                title: '仪表盘',
                href: dashboard(),
                icon: LayoutDashboard,
            },
        ];
    }

    return menus.value.map((menu) => ({
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
            <TenantSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <div
                v-if="!hasConfiguredMenus"
                class="mx-2 rounded-md border border-dashed border-sidebar-border/70 p-3 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden"
            >
                <p class="font-medium text-sidebar-foreground">
                    当前公司尚未配置菜单
                </p>
                <p class="mt-1">
                    {{
                        isPlatformAdmin
                            ? '已显示平台管理入口，可在菜单管理中完成配置。'
                            : '请切换公司或联系管理员配置菜单。'
                    }}
                </p>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
