<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AppLinkButton from '@/components/app/AppLinkButton.vue';
import { dashboard } from '@/routes';

const status = usePage().props.status as string | undefined;
</script>

<template>
    <Head title="子系统未登录" />
    <main
        class="flex min-h-screen items-center justify-center bg-background p-6"
    >
        <ElResult
            icon="warning"
            title="子系统尚未登录"
            :sub-title="status ?? '尚未建立本地 session。'"
        >
            <template #extra
                ><AppLinkButton :href="dashboard()" type="primary"
                    >返回 AUC 工作台</AppLinkButton
                ></template
            >
        </ElResult>
    </main>
</template>
