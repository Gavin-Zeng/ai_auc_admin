<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLinkButton from '@/components/app/AppLinkButton.vue';
import { dashboard, login } from '@/routes';

withDefaults(defineProps<{ canRegister: boolean }>(), { canRegister: true });
</script>

<template>
    <Head title="AUC 后台" />

    <main
        class="flex min-h-screen items-center bg-background px-6 py-10 text-foreground"
    >
        <section class="mx-auto w-full max-w-5xl">
            <div class="max-w-2xl space-y-5">
                <h1 class="text-4xl font-semibold md:text-5xl">AUC 后台</h1>
                <p class="text-base text-muted-foreground">
                    面向内部业务系统的统一登录和权限中心。登录后可按公司查看可访问应用，并通过
                    SSO 进入各业务后台。
                </p>
                <AppLinkButton
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    type="primary"
                    size="large"
                >
                    进入工作台
                </AppLinkButton>
                <AppLinkButton
                    v-else
                    :href="login()"
                    type="primary"
                    size="large"
                    >登录</AppLinkButton
                >
            </div>
        </section>
    </main>
</template>
