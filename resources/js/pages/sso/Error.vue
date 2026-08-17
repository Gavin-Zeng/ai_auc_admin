<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLinkButton from '@/components/app/AppLinkButton.vue';
import { dashboard } from '@/routes';

defineProps<{ message: string; status: number }>();
</script>

<template>
    <Head title="SSO 跳转失败" />
    <main
        class="flex min-h-screen items-center justify-center bg-background p-6"
    >
        <ElResult
            icon="error"
            title="SSO 跳转失败"
            :sub-title="`错误码 ${status}：${message}`"
        >
            <template #extra
                ><AppLinkButton :href="dashboard()" type="primary"
                    >返回 AUC 工作台</AppLinkButton
                ></template
            >
        </ElResult>
    </main>
</template>
