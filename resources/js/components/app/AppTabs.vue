<script setup lang="ts">
import { PanelLeftClose, PanelLeftOpen } from 'lucide-vue-next';
import type { AppTab } from '@/lib/appTabs';

defineProps<{
    tabs: AppTab[];
    activeKey: string;
    collapsed: boolean;
}>();

const emit = defineEmits<{
    activate: [key: string];
    close: [key: string];
    toggle: [];
}>();
</script>

<template>
    <div
        v-if="tabs.length"
        class="app-tabs flex shrink-0 border-b border-border"
    >
        <div class="app-tabs-toggle items-center justify-center">
            <ElTooltip
                :content="collapsed ? '展开侧栏' : '收起侧栏'"
                placement="bottom"
            >
                <ElButton
                    text
                    circle
                    size="small"
                    :aria-label="collapsed ? '展开侧栏' : '收起侧栏'"
                    @click="emit('toggle')"
                >
                    <PanelLeftOpen v-if="collapsed" class="size-4" />
                    <PanelLeftClose v-else class="size-4" />
                </ElButton>
            </ElTooltip>
        </div>

        <ElTabs
            class="min-w-0 flex-1"
            :model-value="activeKey"
            :closable="tabs.length > 1"
            @tab-change="(key) => emit('activate', String(key))"
            @tab-remove="(key) => emit('close', String(key))"
        >
            <ElTabPane v-for="tab in tabs" :key="tab.key" :name="tab.key">
                <template #label>
                    <ElTooltip :content="tab.title" placement="bottom">
                        <span class="block max-w-40 truncate">{{
                            tab.title
                        }}</span>
                    </ElTooltip>
                </template>
            </ElTabPane>
        </ElTabs>
    </div>
</template>
