<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink, ShieldCheck } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { AucApplication, AucTenant } from '@/types';

defineProps<{
    tenant: AucTenant;
    applications: AucApplication[];
    identity: {
        roles: string[];
        permissions: string[];
        permission_version: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'AUC Workspace',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="AUC Workspace" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <section class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-normal">
                        AUC Workspace
                    </h1>
                    <Badge variant="secondary">{{ tenant.name }}</Badge>
                </div>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Centralized authentication, tenant access, and application
                    entry points for the current tenant.
                </p>
            </div>
            <div
                class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-2 text-sm font-medium">
                    <ShieldCheck class="size-4" />
                    Permission snapshot
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <div class="text-muted-foreground">Roles</div>
                        <div class="font-semibold">
                            {{ identity.roles.length }}
                        </div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Permissions</div>
                        <div class="font-semibold">
                            {{ identity.permissions.length }}
                        </div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Version</div>
                        <div class="font-semibold">
                            {{ identity.permission_version }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <a
                v-for="application in applications"
                :key="application.id"
                :href="application.authorize_url"
                class="group rounded-lg border border-sidebar-border/70 bg-background p-4 transition hover:border-primary/60 hover:shadow-sm dark:border-sidebar-border"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold">
                            {{ application.name }}
                        </div>
                        <div class="mt-1 text-sm text-muted-foreground">
                            {{ application.base_url }}
                        </div>
                    </div>
                    <Button size="icon" variant="ghost" class="shrink-0">
                        <ExternalLink class="size-4" />
                    </Button>
                </div>
            </a>
        </div>

        <div
            v-if="applications.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            No accessible applications for the current tenant.
        </div>
    </div>
</template>
