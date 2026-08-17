<script setup lang="ts">
import { Menu } from 'lucide-vue-next';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const emit = defineEmits<{ openMobile: [] }>();
</script>

<template>
    <header
        class="flex h-(--app-header-height) shrink-0 items-center gap-2 border-b border-sidebar-border bg-background px-4"
    >
        <ElTooltip content="打开导航" placement="bottom">
            <ElButton
                class="app-mobile-menu-button"
                text
                circle
                aria-label="打开导航"
                @click="emit('openMobile')"
            >
                <Menu class="size-5" />
            </ElButton>
        </ElTooltip>
        <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
    </header>
</template>
