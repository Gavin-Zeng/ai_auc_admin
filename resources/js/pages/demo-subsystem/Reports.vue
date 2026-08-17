<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLinkButton from '@/components/app/AppLinkButton.vue';
import { dashboard } from '@/routes/demo-subsystem';

defineProps<{
    identity: { user: { name: string }; permissions: string[] } | null;
}>();
</script>

<template>
    <Head title="Demo 报表" />
    <main
        class="flex min-h-screen items-center justify-center bg-background p-6"
    >
        <ElResult
            icon="success"
            title="受保护报表页面"
            :sub-title="`当前用户：${identity?.user.name ?? '未知'}`"
        >
            <template #extra
                ><AppLinkButton :href="dashboard()" type="primary"
                    >返回子系统首页</AppLinkButton
                ></template
            >
        </ElResult>
    </main>
</template>
