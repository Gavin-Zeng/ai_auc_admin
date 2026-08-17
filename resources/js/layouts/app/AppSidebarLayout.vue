<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppTabs from '@/components/app/AppTabs.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { useAppTabs } from '@/composables/useAppTabs';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const collapsed = ref(page.props.sidebarOpen === false);
const mobileOpen = ref(false);
const pageTitleMap: Record<string, string> = {
    Dashboard: 'AUC 工作台',
    'admin/Diagnostics': '运维诊断',
    'admin/GamePermissions': '游戏权限管理',
    'demo-subsystem/Dashboard': 'Demo 子系统',
    'demo-subsystem/Reports': '受保护报表',
};

const { tabs, activeKey, openTab, activateTab, closeTab } = useAppTabs({
    fallbackTitle: 'AUC 工作台',
    getCurrentTitle: () => {
        const resource = page.props.resource as { label?: string } | undefined;
        const breadcrumbTitle = props.breadcrumbs.at(-1)?.title;

        const browserTitle =
            typeof document !== 'undefined'
                ? document.title.split(' - ')[0]
                : '';

        const title =
            resource?.label ??
            breadcrumbTitle ??
            pageTitleMap[page.component] ??
            browserTitle;

        return title || '页面';
    },
});

function handleNavigate(item?: NavItem): void {
    mobileOpen.value = false;
    openTab(
        item ? toUrl(item.href) : toUrl(dashboard()),
        item?.title ?? 'AUC 工作台',
    );
}

function toggleSidebar(): void {
    collapsed.value = !collapsed.value;

    if (typeof document !== 'undefined') {
        document.cookie = `sidebar_state=${collapsed.value ? 'false' : 'true'}; path=/; max-age=31536000; SameSite=Lax`;
    }
}

function handleShortcut(event: KeyboardEvent): void {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'b') {
        event.preventDefault();
        toggleSidebar();
    }
}

onMounted(() => window.addEventListener('keydown', handleShortcut));
onBeforeUnmount(() => window.removeEventListener('keydown', handleShortcut));
</script>

<template>
    <ElContainer class="min-h-screen bg-background">
        <ElAside
            class="app-desktop-sidebar border-r border-sidebar-border"
            :width="
                collapsed
                    ? 'var(--app-sidebar-collapsed-width)'
                    : 'var(--app-sidebar-width)'
            "
        >
            <AppSidebar :collapsed="collapsed" @navigate="handleNavigate" />
        </ElAside>

        <ElDrawer
            v-model="mobileOpen"
            direction="ltr"
            size="min(88vw, 320px)"
            :with-header="false"
            class="app-mobile-sidebar-drawer"
        >
            <AppSidebar @navigate="handleNavigate" />
        </ElDrawer>

        <ElContainer class="min-w-0">
            <ElHeader class="app-mobile-header h-auto p-0">
                <AppSidebarHeader
                    :breadcrumbs="breadcrumbs"
                    @open-mobile="mobileOpen = true"
                />
            </ElHeader>
            <AppTabs
                :tabs="tabs"
                :active-key="activeKey"
                :collapsed="collapsed"
                @activate="activateTab"
                @close="closeTab"
                @toggle="toggleSidebar"
            />
            <ElMain class="min-w-0 p-0"><slot /></ElMain>
        </ElContainer>
    </ElContainer>
</template>
