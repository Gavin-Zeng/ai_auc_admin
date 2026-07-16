<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { switchMethod as switchTenant } from '@/routes/tenant';

const page = usePage();
const currentTenant = computed(() => page.props.auth.tenant);
const tenants = computed(() => page.props.auth.tenants ?? []);

function selectTenant(tenantId: number): void {
    if (tenantId === currentTenant.value?.id) {
        return;
    }

    router.post(switchTenant().url, { tenant_id: tenantId });
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        class="h-auto min-h-10 border border-sidebar-border/70"
                        :tooltip="currentTenant?.name ?? '选择公司'"
                    >
                        <Building2 class="size-4" />
                        <div
                            class="grid min-w-0 flex-1 text-left leading-tight"
                        >
                            <span class="text-xs text-muted-foreground">
                                当前公司
                            </span>
                            <span class="truncate font-medium">
                                {{ currentTenant?.name ?? '请选择公司' }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56"
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel>切换公司</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="tenant in tenants"
                        :key="tenant.id"
                        class="cursor-pointer"
                        :disabled="tenant.id === currentTenant?.id"
                        @select="selectTenant(tenant.id)"
                    >
                        <Building2 class="size-4" />
                        <span class="min-w-0 flex-1 truncate">{{
                            tenant.name
                        }}</span>
                        <span
                            v-if="tenant.status !== 'active'"
                            class="text-xs text-muted-foreground"
                        >
                            已停用
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
