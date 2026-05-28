<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/demo-subsystem';

defineProps<{
    identity: {
        user: { name: string };
        permissions: string[];
    } | null;
}>();
</script>

<template>
    <Head title="Demo 报表" />

    <main class="flex min-h-screen items-center justify-center bg-background p-6">
        <section class="w-full max-w-lg space-y-5 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10">
                <BarChart3 class="size-6 text-primary" />
            </div>
            <div class="space-y-2">
                <h1 class="text-xl font-semibold tracking-normal">
                    受保护报表页面
                </h1>
                <p class="text-sm text-muted-foreground">
                    该页面由子系统本地中间件按权限快照鉴权，不会每次请求回源 AUC。
                </p>
                <p class="text-sm text-muted-foreground">
                    当前用户：{{ identity?.user.name }}
                </p>
            </div>
            <Button as-child>
                <Link :href="dashboard()">返回子系统首页</Link>
            </Button>
        </section>
    </main>
</template>
