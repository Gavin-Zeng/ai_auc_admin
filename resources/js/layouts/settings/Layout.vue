<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const items = [
    { label: '个人资料', url: editProfile().url },
    { label: '安全设置', url: editSecurity().url },
    { label: '外观设置', url: editAppearance().url },
];
const { currentUrl } = useCurrentUrl();
</script>

<template>
    <div class="p-4 md:p-6">
        <header class="mb-5">
            <h1 class="text-xl font-semibold">设置</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                管理个人资料和账号设置。
            </p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[180px_minmax(0,640px)]">
            <ElMenu
                :default-active="currentUrl"
                class="border-r-0"
                @select="(url: string) => router.visit(url)"
            >
                <ElMenuItem
                    v-for="item in items"
                    :key="item.url"
                    :index="item.url"
                >
                    {{ item.label }}
                </ElMenuItem>
            </ElMenu>
            <section class="min-w-0 space-y-10"><slot /></section>
        </div>
    </div>
</template>
