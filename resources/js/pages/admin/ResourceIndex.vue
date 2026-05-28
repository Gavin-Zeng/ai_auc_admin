<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Search, ShieldOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type FieldOption = string | { value: number | string; label: string };

type FieldConfig = {
    name: string;
    label: string;
    description?: string;
    type: 'text' | 'number' | 'select' | 'textarea' | 'checkbox' | 'multiselect';
    required?: boolean;
    options?: FieldOption[];
};

type ResourceConfig = {
    name: string;
    label: string;
    description?: string;
    createLabel?: string;
    storeUrl?: string;
    readOnly?: boolean;
    fields: FieldConfig[];
    columns: string[];
    actions?: string[];
};

type PaginatedItems = {
    data: Record<string, any>[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    resource: ResourceConfig;
    items: PaginatedItems;
    filters: { search?: string };
    options: Record<string, FieldOption[]>;
}>();

const editing = ref<Record<string, any> | null>(null);
const showForm = ref(false);
const search = ref(props.filters.search ?? '');
const page = usePage();
const rotatedSecret = computed(() => page.props.secret as string | undefined);

const blankValues = computed(() =>
    Object.fromEntries(
        props.resource.fields.map((field) => [
            field.name,
            field.type === 'checkbox'
                ? false
                : field.type === 'multiselect'
                  ? []
                  : '',
        ]),
    ),
);

const form = useForm<Record<string, any>>({ ...blankValues.value });

const displayLabels: Record<string, string> = {
    action: '操作',
    application_id: '所属应用',
    base_url: '基础地址',
    client_id: '客户端 ID',
    code: '编码',
    created_at: '创建时间',
    description: '描述',
    domain: '域名',
    email: '邮箱',
    group: '分组',
    href: '链接',
    ip_address: 'IP 地址',
    is_platform_admin: '平台超管',
    is_system: '系统内置',
    is_visible: '是否显示',
    name: '名称',
    parent_id: '父级菜单',
    redirect_uri: '回调地址',
    sort_order: '排序',
    status: '状态',
    subject_id: '对象 ID',
    subject_type: '对象类型',
    title: '标题',
};

const valueLabels: Record<string, string> = {
    active: '启用',
    disabled: '停用',
};

function optionValue(option: FieldOption): string {
    return typeof option === 'string' ? option : String(option.value);
}

function optionLabel(option: FieldOption): string {
    if (typeof option === 'string') {
        return valueLabels[option] ?? option;
    }

    return option.label;
}

function fieldOptions(field: FieldConfig): FieldOption[] {
    return field.options ?? props.options[field.name] ?? [];
}

function startCreate() {
    editing.value = null;
    form.defaults({ ...blankValues.value });
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function startEdit(item: Record<string, any>) {
    editing.value = item;
    const values = { ...blankValues.value };

    for (const field of props.resource.fields) {
        values[field.name] = item[field.name] ?? values[field.name];
    }

    form.defaults(values);
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/${props.resource.name}/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (showForm.value = false),
        });

        return;
    }

    form.post(props.resource.storeUrl ?? `/${props.resource.name}`, {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    });
}

function disable(item: Record<string, any>) {
    router.delete(`/${props.resource.name}/${item.id}`, {
        preserveScroll: true,
    });
}

function rotateSecret(item: Record<string, any>) {
    router.post(`/${props.resource.name}/${item.id}/rotate-secret`, {}, {
        preserveScroll: true,
    });
}

function runSearch() {
    router.get(
        `/${props.resource.name}`,
        { search: search.value },
        { preserveState: true, preserveScroll: true },
    );
}

function columnLabel(column: string): string {
    return displayLabels[column] ?? column;
}

function toggleMulti(field: FieldConfig, value: string) {
    const current = new Set((form[field.name] ?? []).map(String));

    if (current.has(value)) {
        current.delete(value);
    } else {
        current.add(value);
    }

    form[field.name] = [...current];
}

function displayValue(item: Record<string, any>, column: string): string {
    const value = item[column];

    if (typeof value === 'boolean') {
        return value ? '是' : '否';
    }

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'string') {
        return valueLabels[value] ?? value;
    }

    return value ?? '';
}
</script>

<template>
    <Head :title="resource.label" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-normal">
                    {{ resource.label }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        resource.description ??
                        'AUC 集中式权限与 SSO 管理后台。'
                    }}
                </p>
            </div>
            <Button v-if="!resource.readOnly" @click="startCreate">
                <Plus class="size-4" />
                {{ resource.createLabel ?? '新增' }}
            </Button>
        </div>

        <div
            v-if="rotatedSecret"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <div class="font-medium">新的应用密钥只显示一次</div>
            <div class="mt-2 break-all font-mono">{{ rotatedSecret }}</div>
        </div>

        <div
            v-if="resource.name === 'menus'"
            class="rounded-lg border border-sidebar-border/70 p-4 text-sm text-muted-foreground"
        >
            菜单按父级菜单和排序字段组成树形结构。隐藏菜单只影响入口可见性，不替代服务端接口鉴权。
        </div>

        <div class="flex items-center gap-2">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="搜索"
                    @keyup.enter="runSearch"
                />
            </div>
            <Button variant="secondary" @click="runSearch">搜索</Button>
        </div>

        <form
            v-if="showForm && !resource.readOnly"
            class="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 md:grid-cols-2 dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div
                v-for="field in resource.fields"
                :key="field.name"
                class="space-y-2"
            >
                <Label :for="field.name">{{ field.label }}</Label>

                <textarea
                    v-if="field.type === 'textarea'"
                    :id="field.name"
                    v-model="form[field.name]"
                    class="border-input min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                />

                <Checkbox
                    v-else-if="field.type === 'checkbox'"
                    :id="field.name"
                    :model-value="Boolean(form[field.name])"
                    @update:model-value="form[field.name] = $event"
                />

                <Select
                    v-else-if="field.type === 'select'"
                    v-model="form[field.name]"
                >
                    <SelectTrigger>
                        <SelectValue :placeholder="field.label" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in fieldOptions(field)"
                            :key="optionValue(option)"
                            :value="optionValue(option)"
                        >
                            {{ optionLabel(option) }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div
                    v-else-if="field.type === 'multiselect'"
                    class="max-h-36 space-y-2 overflow-y-auto rounded-md border border-sidebar-border/70 p-3"
                >
                    <label
                        v-for="option in fieldOptions(field)"
                        :key="optionValue(option)"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :model-value="
                                (form[field.name] ?? [])
                                    .map(String)
                                    .includes(optionValue(option))
                            "
                            @update:model-value="
                                toggleMulti(field, optionValue(option))
                            "
                        />
                        <span>{{ optionLabel(option) }}</span>
                    </label>
                </div>

                <Input
                    v-else
                    :id="field.name"
                    v-model="form[field.name]"
                    :type="field.type"
                />

                <div v-if="form.errors[field.name]" class="text-sm text-red-600">
                    {{ form.errors[field.name] }}
                </div>
            </div>

            <div class="flex gap-2 md:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? '更新' : '创建' }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    @click="showForm = false"
                >
                    取消
                </Button>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th
                            v-for="column in resource.columns"
                            :key="column"
                            class="px-3 py-2 font-medium"
                        >
                            {{ columnLabel(column) }}
                        </th>
                        <th class="w-44 px-3 py-2 font-medium">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items.data"
                        :key="item.id"
                        class="border-t border-sidebar-border/70"
                    >
                        <td
                            v-for="column in resource.columns"
                            :key="column"
                            class="px-3 py-2"
                        >
                            <Badge v-if="column === 'status'" variant="secondary">
                                {{ displayValue(item, column) }}
                            </Badge>
                            <span v-else>{{ displayValue(item, column) }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex gap-1">
                                <Button
                                    v-if="!resource.readOnly"
                                    size="icon"
                                    variant="ghost"
                                    @click="startEdit(item)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    v-if="
                                        resource.actions?.includes(
                                            'rotateSecret',
                                        )
                                    "
                                    size="icon"
                                    variant="ghost"
                                    @click="rotateSecret(item)"
                                >
                                    <KeyRound class="size-4" />
                                </Button>
                                <Button
                                    v-if="!resource.readOnly"
                                    size="icon"
                                    variant="ghost"
                                    @click="disable(item)"
                                >
                                    <ShieldOff class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="items.data.length === 0">
                        <td
                            :colspan="resource.columns.length + 1"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            暂无数据。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                v-for="link in items.links"
                :key="link.label"
                :disabled="!link.url"
                :variant="link.active ? 'default' : 'secondary'"
                size="sm"
                @click="link.url && router.visit(link.url)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
