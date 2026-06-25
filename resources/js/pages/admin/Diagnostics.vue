<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

type DiagnosticCheck = {
    key: string;
    label: string;
    passed: boolean;
    severity: string;
    detail: string | null;
};

const props = defineProps<{
    report: {
        passed: boolean;
        checks: DiagnosticCheck[];
    };
}>();
</script>

<template>
    <Head title="运维诊断" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-normal">运维诊断</h1>
                <p class="text-sm text-muted-foreground">
                    检查默认公司、管理员、权限、菜单、应用和 SSO 接入配置。
                </p>
            </div>
            <Badge :variant="props.report.passed ? 'secondary' : 'destructive'">
                {{ props.report.passed ? '全部通过' : '存在异常' }}
            </Badge>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div
                v-for="check in report.checks"
                :key="check.key"
                class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <CheckCircle2
                    v-if="check.passed"
                    class="mt-0.5 size-5 text-emerald-600"
                />
                <XCircle v-else class="mt-0.5 size-5 text-red-600" />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium">{{ check.label }}</p>
                        <Badge :variant="check.passed ? 'secondary' : 'destructive'">
                            {{ check.passed ? '正常' : '异常' }}
                        </Badge>
                    </div>
                    <p
                        v-if="check.detail"
                        class="mt-2 break-all text-sm text-muted-foreground"
                    >
                        {{ check.detail }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
