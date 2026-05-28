<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { Button } from '@/components/ui/button';

defineProps<{
    message: string;
    status: number;
}>();
</script>

<template>
    <Head title="SSO 跳转失败" />

    <main class="flex min-h-screen items-center justify-center bg-background p-6">
        <section class="w-full max-w-md space-y-5 text-center">
            <div
                class="mx-auto flex size-12 items-center justify-center rounded-full bg-destructive/10 text-destructive"
            >
                <AlertTriangle class="size-6" />
            </div>

            <div class="space-y-2">
                <p class="text-sm text-muted-foreground">错误码 {{ status }}</p>
                <h1 class="text-xl font-semibold tracking-normal">
                    SSO 跳转失败
                </h1>
                <p class="text-sm text-muted-foreground">{{ message }}</p>
            </div>

            <Button as-child>
                <Link :href="dashboard()">返回 AUC 工作台</Link>
            </Button>
        </section>
    </main>
</template>
