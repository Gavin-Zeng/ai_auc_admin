<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: NavItem[];
        collapsed?: boolean;
    }>(),
    {
        collapsed: false,
    },
);

const emit = defineEmits<{
    navigate: [item?: NavItem];
}>();

const { currentUrl } = useCurrentUrl();

function navigate(path: string): void {
    router.visit(path);
    emit(
        'navigate',
        props.items.find((item) => toUrl(item.href) === path),
    );
}
</script>

<template>
    <div class="min-h-0 flex-1 overflow-y-auto py-3">
        <div
            v-if="!collapsed"
            class="px-5 pb-2 text-xs font-medium text-muted-foreground"
        >
            平台
        </div>
        <ElMenu
            :default-active="currentUrl"
            :collapse="collapsed"
            :collapse-transition="false"
            class="app-nav-menu border-r-0"
            @select="navigate"
        >
            <ElMenuItem
                v-for="item in items"
                :key="item.title"
                :index="toUrl(item.href)"
                class="gap-3"
            >
                <component
                    v-if="item.icon"
                    :is="item.icon"
                    class="size-4 shrink-0"
                />
                <template #title>{{ item.title }}</template>
            </ElMenuItem>
        </ElMenu>
    </div>
</template>
