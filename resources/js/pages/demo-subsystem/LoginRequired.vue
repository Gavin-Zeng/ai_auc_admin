<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { LogIn } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const status = usePage().props.status as string | undefined;
</script>

<template>
    <Head title="子系统未登录" />

    <main class="flex min-h-screen items-center justify-center bg-background p-6">
        <section class="w-full max-w-md space-y-5 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10">
                <LogIn class="size-6 text-primary" />
            </div>
            <div class="space-y-2">
                <h1 class="text-xl font-semibold tracking-normal">
                    子系统尚未建立本地 session
                </h1>
                <p class="text-sm text-muted-foreground">
                    请先在 AUC 工作台点击应用图标，通过 SSO 回调完成免登录接入。
                </p>
                <p v-if="status" class="text-sm text-destructive">
                    {{ status }}
                </p>
            </div>
            <Button as-child>
                <Link :href="dashboard()">返回 AUC 工作台</Link>
            </Button>
        </section>
    </main>
</template>
