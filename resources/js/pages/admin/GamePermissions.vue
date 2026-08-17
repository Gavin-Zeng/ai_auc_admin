<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppEmptyState from '@/components/app/AppEmptyState.vue';
import AppLinkButton from '@/components/app/AppLinkButton.vue';
import AppPageHeader from '@/components/app/AppPageHeader.vue';
import { index as usersIndex } from '@/routes/users';
import { update } from '@/routes/users/game-permissions';

type TreeNode = {
    key: string;
    label: string;
    children?: TreeNode[];
};

type TreeRef = {
    getCheckedKeys: () => Array<number | string>;
};

const props = defineProps<{
    user: { id: number; name: string; account: string } | null;
    motherGames: {
        id: string;
        name: string;
        children: { id: string; name: string }[];
    }[];
    permissions: { scope_type: string; scope_key: string }[];
    tenantId: number;
}>();

const tree = ref<TreeRef>();
const defaultCheckedKeys = props.permissions.map(
    (permission) => `${permission.scope_type}:${permission.scope_key}`,
);
const treeData = computed<TreeNode[]>(() => [
    { key: 'ALL:*', label: '所有游戏（包含未来新增游戏）' },
    ...props.motherGames.map((mother) => ({
        key: `MOTHER:${mother.id}`,
        label: `${mother.name}（母游戏）`,
        children: mother.children.map((child) => ({
            key: `CHILD:${child.id}`,
            label: child.name,
        })),
    })),
]);
const form = useForm({
    tenant_id: props.tenantId,
    permissions: [] as { scope_type: string; scope_key: string }[],
});

function save(): void {
    form.permissions = (tree.value?.getCheckedKeys() ?? []).map((value) => {
        const [scope_type, scope_key] = String(value).split(':');

        return { scope_type, scope_key };
    });
    form.put(update(props.user?.id ?? 0).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="游戏权限管理" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <AppPageHeader
            title="游戏权限管理"
            description="ALL、母游戏和子游戏权限相互独立，勾选父级不会自动改变子级。"
        >
            <template #actions>
                <AppLinkButton :href="usersIndex()">返回用户列表</AppLinkButton>
            </template>
        </AppPageHeader>

        <AppEmptyState v-if="!user" description="未找到需要配置的用户" />

        <template v-else>
            <ElDescriptions :column="2" border class="max-w-3xl">
                <ElDescriptionsItem label="用户">{{
                    user.name
                }}</ElDescriptionsItem>
                <ElDescriptionsItem label="账号">{{
                    user.account
                }}</ElDescriptionsItem>
            </ElDescriptions>

            <section class="max-w-3xl">
                <ElTree
                    ref="tree"
                    :data="treeData"
                    node-key="key"
                    show-checkbox
                    check-strictly
                    default-expand-all
                    :default-checked-keys="defaultCheckedKeys"
                    :props="{ label: 'label', children: 'children' }"
                    class="rounded-md border border-border p-3"
                />
            </section>

            <div>
                <ElButton
                    type="primary"
                    :loading="form.processing"
                    @click="save"
                >
                    保存权限
                </ElButton>
            </div>
        </template>
    </div>
</template>
