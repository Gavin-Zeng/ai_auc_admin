<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { update } from '@/routes/users/game-permissions';

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

const selected = ref(
    props.permissions.map(
        (permission) => `${permission.scope_type}:${permission.scope_key}`,
    ),
);
const all = computed(() => selected.value.includes('ALL:*'));
const form = useForm({
    tenant_id: props.tenantId,
    permissions: [] as { scope_type: string; scope_key: string }[],
});

function toggle(key: string, checked: boolean): void {
    selected.value = checked
        ? [...new Set([...selected.value, key])]
        : selected.value.filter((value) => value !== key);
}

function save(): void {
    form.permissions = selected.value.map((key) => {
        const [scope_type, scope_key] = key.split(':');

        return { scope_type, scope_key };
    });
    form.put(update(props.user?.id ?? 0).url);
}
</script>

<template>
    <Head title="游戏权限管理" />

    <div class="flex flex-col gap-6 p-6">
        <div>
            <h1 class="text-xl font-semibold">游戏权限管理</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                为当前用户选择可访问的游戏范围。
            </p>
        </div>

        <div v-if="user" class="flex max-w-3xl flex-col gap-4 rounded-lg border p-5">
            <h2 class="font-semibold">配置 {{ user.name }}（{{ user.account }}）的游戏权限</h2>

            <label class="flex items-center gap-2">
                <Checkbox
                    :model-value="all"
                    @update:model-value="(value) => toggle('ALL:*', Boolean(value))"
                />
                所有游戏（包含未来新增游戏）
            </label>

            <div
                v-for="mother in motherGames"
                :key="mother.id"
                class="rounded-md border p-3"
            >
                <label class="flex items-center gap-2 font-medium">
                    <Checkbox
                        :model-value="selected.includes(`MOTHER:${mother.id}`)"
                        @update:model-value="
                            (value) =>
                                toggle(`MOTHER:${mother.id}`, Boolean(value))
                        "
                    />
                    {{ mother.name }}（母游戏）
                </label>
                <label
                    v-for="child in mother.children"
                    :key="child.id"
                    class="mt-2 ml-7 flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="selected.includes(`CHILD:${child.id}`)"
                        @update:model-value="
                            (value) =>
                                toggle(`CHILD:${child.id}`, Boolean(value))
                        "
                    />
                    {{ child.name }}
                </label>
            </div>

            <Button :disabled="form.processing" class="self-start" @click="save">
                保存权限
            </Button>
        </div>
    </div>
</template>
