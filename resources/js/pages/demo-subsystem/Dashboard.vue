<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { logout, reports } from '@/routes/demo-subsystem';

defineProps<{
    identity: {
        user: { id: number; name: string; email: string };
        tenant: { id: number; code: string; name: string; status: string };
        roles: string[];
        permissions: string[];
        permission_version: number;
        session_expires_at: string;
    };
    canViewReports: boolean;
}>();
</script>

<template>
    <Head title="Demo 子系统" />

    <main class="min-h-screen bg-background p-6">
        <section class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <ShieldCheck class="size-4" />
                        Laravel 子系统接入示例
                    </div>
                    <h1 class="text-2xl font-semibold tracking-normal">
                        已通过 AUC 建立本地 session
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        当前页面读取的是子系统本地 session 中的 AUC 身份和权限快照。
                    </p>
                </div>

                <Form v-bind="logout.form()" #default="{ processing }">
                    <Button variant="secondary" :disabled="processing">
                        子系统退出
                    </Button>
                </Form>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="text-sm text-muted-foreground">当前用户</div>
                    <div class="mt-2 font-medium">{{ identity.user.name }}</div>
                    <div class="text-sm text-muted-foreground">
                        {{ identity.user.email }}
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="text-sm text-muted-foreground">当前租户</div>
                    <div class="mt-2 font-medium">{{ identity.tenant.name }}</div>
                    <div class="text-sm text-muted-foreground">
                        {{ identity.tenant.code }} · {{ identity.tenant.status }}
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 text-sm font-medium">权限快照</div>
                <div class="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <div class="text-muted-foreground">角色</div>
                        <div class="font-semibold">{{ identity.roles.length }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">权限</div>
                        <div class="font-semibold">
                            {{ identity.permissions.length }}
                        </div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">版本</div>
                        <div class="font-semibold">
                            {{ identity.permission_version }}
                        </div>
                    </div>
                </div>
            </div>

            <Button v-if="canViewReports" as-child>
                <Link :href="reports()">访问受保护报表</Link>
            </Button>
        </section>
    </main>
</template>
