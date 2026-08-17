<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLinkButton from '@/components/app/AppLinkButton.vue';
import { formatDateTime } from '@/lib/formatters';
import { logout, reports } from '@/routes/demo-subsystem';
import { refresh } from '@/routes/demo-subsystem/permissions';

defineProps<{
    identity: {
        user: { id: number; name: string; email: string };
        tenant: { id: number; code: string; name: string; status: string };
        roles: string[];
        permissions: string[];
        permission_version: number;
        session_expires_at: string;
    };
    canViewReports: boolean;
}>();

const refreshForm = useForm({});
const logoutForm = useForm({});
</script>

<template>
    <Head title="Demo 子系统" />

    <main class="min-h-screen bg-background p-4 md:p-6">
        <section class="mx-auto max-w-4xl space-y-5">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Demo 子系统</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        已通过 AUC 建立本地 session。
                    </p>
                </div>
                <div class="flex gap-2">
                    <ElButton
                        :loading="refreshForm.processing"
                        @click="refreshForm.post(refresh().url)"
                        >刷新权限快照</ElButton
                    >
                    <ElButton
                        :loading="logoutForm.processing"
                        @click="logoutForm.post(logout().url)"
                        >子系统退出</ElButton
                    >
                </div>
            </header>

            <ElDescriptions :column="2" border>
                <ElDescriptionsItem label="用户">{{
                    identity.user.name
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="邮箱">{{
                    identity.user.email
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="租户">{{
                    identity.tenant.name
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="租户编码">{{
                    identity.tenant.code
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="角色数量">{{
                    identity.roles.length
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="权限数量">{{
                    identity.permissions.length
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="权限版本">{{
                    identity.permission_version
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="会话过期时间">{{
                    formatDateTime(identity.session_expires_at)
                }}</ElDescriptionsItem>
            </ElDescriptions>

            <AppLinkButton
                v-if="canViewReports"
                :href="reports()"
                type="primary"
                >访问受保护报表</AppLinkButton
            >
        </section>
    </main>
</template>
