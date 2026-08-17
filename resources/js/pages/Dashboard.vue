<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AppWindow, ExternalLink, Globe2, Headset } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import AppEmptyState from '@/components/app/AppEmptyState.vue';
import AppStatusTag from '@/components/app/AppStatusTag.vue';
import { dashboard } from '@/routes';
import type { AucApplication, AucTenant } from '@/types';

defineProps<{
    tenant: AucTenant | null;
    applications: AucApplication[];
    identity: {
        roles: string[];
        permissions: string[];
        permission_version: number;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'AUC 工作台', href: dashboard() }] },
});

function applicationIcon(application: AucApplication): LucideIcon {
    const applicationKey =
        `${application.client_id} ${application.name}`.toLowerCase();

    if (applicationKey.includes('gm') || application.name.includes('客服')) {
        return Headset;
    }

    if (
        applicationKey.includes('overseas') ||
        application.name.includes('海外')
    ) {
        return Globe2;
    }

    return AppWindow;
}
</script>

<template>
    <Head title="AUC 工作台" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <section>
            <h2 class="mb-3 text-base font-medium">业务系统</h2>
            <div
                v-if="applications.length"
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
            >
                <ElCard
                    v-for="application in applications"
                    :key="application.id"
                    shadow="never"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                aria-hidden="true"
                            >
                                <component
                                    :is="applicationIcon(application)"
                                    class="size-5"
                                />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-medium">
                                    {{ application.name }}
                                </h3>
                                <p
                                    class="mt-1 truncate text-sm text-muted-foreground"
                                >
                                    {{ application.base_url ?? '未配置地址' }}
                                </p>
                                <div class="mt-3 flex gap-2">
                                    <AppStatusTag :value="application.status" />
                                    <ElTag
                                        v-if="
                                            identity.roles.includes(
                                                'platform_admin',
                                            )
                                        "
                                        type="info"
                                        effect="plain"
                                        size="small"
                                    >
                                        {{
                                            application.is_available
                                                ? '可进入'
                                                : '待配置'
                                        }}
                                    </ElTag>
                                </div>
                            </div>
                        </div>
                        <ElTooltip
                            :content="
                                application.action_url
                                    ? '在新窗口打开'
                                    : '系统尚未配置'
                            "
                        >
                            <ElButton
                                circle
                                :disabled="!application.action_url"
                                :tag="application.action_url ? 'a' : 'button'"
                                :href="application.action_url ?? undefined"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="打开系统"
                            >
                                <ExternalLink class="size-4" />
                            </ElButton>
                        </ElTooltip>
                    </div>
                </ElCard>
            </div>
            <AppEmptyState v-else description="暂无可访问系统" />
        </section>
    </div>
</template>
