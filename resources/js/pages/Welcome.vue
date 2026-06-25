<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ShieldCheck } from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="AUC 后台" />

    <main
        class="flex min-h-screen items-center bg-background px-6 py-10 text-foreground"
    >
        <section class="mx-auto w-full max-w-5xl">
            <div class="max-w-3xl space-y-6">
                <div
                    class="inline-flex items-center gap-2 rounded-md border border-sidebar-border/70 px-3 py-1 text-sm text-muted-foreground"
                >
                    <ShieldCheck class="size-4" />
                    统一认证 · 集中式权限管理 · 应用入口
                </div>

                <div class="space-y-3">
                    <h1
                        class="text-4xl font-semibold tracking-normal md:text-5xl"
                    >
                        AUC 后台
                    </h1>
                    <p class="max-w-2xl text-base text-muted-foreground">
                        面向内部业务系统的统一登录中心和权限中心。登录后可按公司查看可访问应用，并通过
                        SSO 免登录进入各业务后台。
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                    >
                        进入工作台
                        <ArrowRight class="size-4" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                        >
                            登录
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex items-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium"
                        >
                            注册
                        </Link>
                    </template>
                </div>
            </div>
        </section>
    </main>
</template>
