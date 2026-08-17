<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

withDefaults(
    defineProps<{
        collapsed?: boolean;
    }>(),
    {
        collapsed: false,
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);

function handleCommand(command: 'logout' | 'settings'): void {
    if (command === 'settings') {
        router.visit(editProfile().url);

        return;
    }

    router.flushAll();
    router.post(logout().url);
}
</script>

<template>
    <ElDropdown
        trigger="click"
        placement="top-end"
        class="w-full"
        @command="handleCommand"
    >
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-md px-3 py-2 hover:bg-muted"
            data-test="sidebar-menu-button"
        >
            <UserInfo :user="user" :compact="collapsed" />
            <ChevronsUpDown v-if="!collapsed" class="ml-auto size-4" />
        </button>
        <template #dropdown>
            <ElDropdownMenu>
                <ElDropdownItem command="settings">设置</ElDropdownItem>
                <ElDropdownItem divided command="logout">
                    <span data-test="logout-button">退出登录</span>
                </ElDropdownItem>
            </ElDropdownMenu>
        </template>
    </ElDropdown>
</template>
