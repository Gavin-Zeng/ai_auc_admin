<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppPageHeader from '@/components/app/AppPageHeader.vue';
import AppStatusTag from '@/components/app/AppStatusTag.vue';

type DiagnosticCheck = {
    key: string;
    label: string;
    passed: boolean;
    severity: string;
    detail: string | null;
};

defineProps<{ report: { passed: boolean; checks: DiagnosticCheck[] } }>();
</script>

<template>
    <Head title="运维诊断" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <AppPageHeader
            title="运维诊断"
            description="检查默认公司、管理员、权限、菜单、应用和 SSO 接入配置。"
        >
            <template #actions
                ><AppStatusTag
                    :value="report.passed"
                    :label="report.passed ? '全部通过' : '存在异常'"
            /></template>
        </AppPageHeader>

        <ElTable :data="report.checks" row-key="key" class="app-table w-full">
            <ElTableColumn label="检查项" prop="label" min-width="180" />
            <ElTableColumn label="状态" width="100" align="center">
                <template #default="{ row }"
                    ><AppStatusTag
                        :value="row.passed"
                        :label="row.passed ? '正常' : '异常'"
                /></template>
            </ElTableColumn>
            <ElTableColumn label="级别" prop="severity" width="100" />
            <ElTableColumn label="详情" min-width="260">
                <template #default="{ row }">
                    <ElTooltip
                        v-if="row.detail"
                        :content="row.detail"
                        placement="top"
                    >
                        <span class="block truncate">{{ row.detail }}</span>
                    </ElTooltip>
                    <span v-else class="text-muted-foreground">—</span>
                </template>
            </ElTableColumn>
        </ElTable>
    </div>
</template>
