<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
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
import {
    rotateSecret as rotateApplicationSecret,
    store as storeApplication,
} from '@/routes/applications';
import { index as userGamePermissions } from '@/routes/users/game-permissions';

type FieldOption =
    | string
    | {
          value: number | string;
          label: string;
          tenant_id?: number | string | null;
      };

type FieldConfig = {
    name: string;
    label: string;
    description?: string;
    type:
        | 'text'
        | 'password'
        | 'number'
        | 'select'
        | 'textarea'
        | 'checkbox'
        | 'multiselect';
    required?: boolean;
    options?: FieldOption[];
    default?: unknown;
    createOnly?: boolean;
    updateOnly?: boolean;
    span?: 1 | 2;
    group?: string;
    platformOnly?: boolean;
    generatePassword?: boolean;
};

type ResourceConfig = {
    name: string;
    label: string;
    description?: string;
    createLabel?: string;
    storeUrl?: string;
    currentTenantId?: number | string | null;
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
    filters: { search?: string; company_id?: number | string | null };
    options: Record<string, FieldOption[]>;
}>();

const editing = ref<Record<string, any> | null>(null);
const showForm = ref(false);
const search = ref(props.filters.search ?? '');
const companyId = ref(
    props.filters.company_id ? String(props.filters.company_id) : 'all',
);
const page = usePage();
const rotatedSecret = ref<string>();
const rotatingApplicationId = ref<number | string | null>(null);
const removeFlashListener = router.on('flash', (event) => {
    const flash = (event as CustomEvent).detail?.flash;
    const secret = flash?.secret;

    if (typeof secret === 'string') {
        rotatedSecret.value = secret;
    }
});
const visibleFields = computed(() =>
    props.resource.fields.filter(
        (field) =>
            !(
                field.platformOnly &&
                !page.props.auth.identity?.is_platform_admin
            ) && (editing.value ? !field.createOnly : !field.updateOnly),
    ),
);
const formPanelClass = computed(() => 'max-w-4xl');

const blankValues = computed(() =>
    Object.fromEntries(
        props.resource.fields.map((field) => [
            field.name,
            field.type === 'checkbox'
                ? Boolean(field.default)
                : field.default !== undefined && field.default !== null
                  ? String(field.default)
                  : field.type === 'multiselect'
                    ? []
                    : '',
        ]),
    ),
);

const form = useForm<Record<string, any>>({ ...blankValues.value });

const displayLabels: Record<string, string> = {
    action: '操作',
    account: '账号',
    application_id: '所属系统',
    base_url: '基础地址',
    client_id: '客户端 ID',
    applications_text: '已开通系统',
    code: '编码',
    company_name: '所属公司',
    company_names: '所属公司',
    created_at: '创建时间',
    description: '描述',
    domain: '域名',
    email: '邮箱',
    group: '分组',
    'games.app_id': '子游戏 app_id',
    'games.name': '子游戏名',
    'games.old_id': '母游戏 id',
    'games.old_name': '母游戏名',
    'games.pkg_name': '包名',
    href: '链接',
    is_owner: '公司超管',
    is_company_admin: '公司超管',
    ip_address: 'IP 地址',
    is_platform_admin: '平台超管',
    is_system: '系统内置',
    is_visible: '是否显示',
    name: '名称',
    operated_at: '操作时间',
    operation_action: '操作动作',
    operation_object: '操作对象',
    operator_name: '操作人',
    parent_id: '父级菜单',
    parent_name: '父级菜单',
    path: '菜单路径',
    redirect_uri: '回调地址',
    request_params: '请求参数',
    sort_order: '排序',
    status: '状态',
    subject_id: '对象 ID',
    subject_type: '对象类型',
    system_name: '所属系统',
    application_name: '所属系统',
    role_name: '角色',
    menus_text: '菜单权限',
    users_count: '成员数量',
    urls_count: '地址数量',
    title: '标题',
};

const valueLabels: Record<string, string> = {
    active: '启用',
    disabled: '停用',
    '1': '启用',
    '0': '停用',
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
    if (
        props.resource.name === 'users' &&
        field.name === 'role_ids' &&
        (form.tenant_id || form.target_tenant_id)
    ) {
        return (props.options[field.name] ?? []).filter((option) => {
            if (typeof option === 'string') {
                return true;
            }

            return (
                String(option.tenant_id ?? '') ===
                String(form.tenant_id || form.target_tenant_id)
            );
        });
    }

    if (
        props.resource.name === 'menus' &&
        field.name === 'parent_id' &&
        form.tenant_id
    ) {
        return (props.options[field.name] ?? []).filter((option) => {
            if (typeof option === 'string') {
                return true;
            }

            return String(option.tenant_id ?? '') === String(form.tenant_id);
        });
    }

    return field.options ?? props.options[field.name] ?? [];
}

function generateRandomPassword(field: FieldConfig) {
    const characters =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    const randomValues = new Uint32Array(16);
    crypto.getRandomValues(randomValues);
    form[field.name] = Array.from(
        randomValues,
        (value) => characters[value % characters.length],
    ).join('');
}

function requestPayload(): Record<string, any> {
    const fieldNames = new Set(visibleFields.value.map((field) => field.name));

    return Object.fromEntries(
        Object.entries(form.data()).filter(([key]) => fieldNames.has(key)),
    );
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
        const value = item[field.name] ?? values[field.name];
        values[field.name] =
            field.type === 'select' && typeof value === 'boolean'
                ? value
                    ? '1'
                    : '0'
                : field.type === 'select' && value !== ''
                  ? String(value)
                  : value;
    }

    form.defaults(values);
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function statusPayload(
    item: Record<string, any>,
    status: boolean,
): Record<string, any> {
    const fieldNames = new Set(
        props.resource.fields
            .filter((field) => !field.createOnly)
            .map((field) => field.name),
    );
    const payload = Object.fromEntries(
        Object.entries(item).filter(([key]) => fieldNames.has(key)),
    );

    payload.status = status;

    if (props.resource.name === 'users') {
        payload.password = '';
    }

    return payload;
}

function submit() {
    form.transform(() => requestPayload());

    if (editing.value) {
        form.put(`/${props.resource.name}/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (showForm.value = false),
            onFinish: () => form.transform((data) => data),
        });

        return;
    }

    const storeUrl =
        props.resource.name === 'applications'
            ? storeApplication().url
            : (props.resource.storeUrl ?? `/${props.resource.name}`);

    form.post(storeUrl, {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
        onFinish: () => form.transform((data) => data),
    });
}

function toggleStatus(item: Record<string, any>) {
    if (props.resource.readOnly) {
        return;
    }

    const currentStatus = Boolean(item.status);
    const nextStatus = !currentStatus;
    const confirmed = confirm(
        currentStatus ? '确定停用该记录吗？' : '确定启用该记录吗？',
    );

    if (!confirmed) {
        return;
    }

    router.put(
        `/${props.resource.name}/${item.id}`,
        statusPayload(item, nextStatus),
        {
            preserveScroll: true,
        },
    );
}

function rotateSecret(item: Record<string, any>) {
    if (!confirm(`确定轮换“${item.name}”的客户端密钥吗？旧密钥将立即失效。`)) {
        return;
    }

    rotatedSecret.value = undefined;
    rotatingApplicationId.value = item.id;

    router.post(
        rotateApplicationSecret(item.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                rotatingApplicationId.value = null;
            },
        },
    );
}

function runSearch() {
    router.get(
        `/${props.resource.name}`,
        {
            search: search.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function columnLabel(column: string): string {
    return (
        displayLabels[`${props.resource.name}.${column}`] ??
        displayLabels[column] ??
        column
    );
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

function fieldClass(field: FieldConfig): string {
    return field.span === 2 ? 'space-y-1.5 md:col-span-2' : 'space-y-1.5';
}

function statusBadgeClass(item: Record<string, any>, column: string): string {
    const value = String(item[column] ?? '');

    if (value === 'true' || value === '1') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300';
    }

    if (value === 'false' || value === '0') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300';
    }

    return '';
}

function statusButtonClass(item: Record<string, any>, column: string): string {
    return `${statusBadgeClass(item, column)} h-7 rounded-full px-3 text-xs font-medium shadow-none transition hover:brightness-95`;
}

function shouldShowActions(): boolean {
    return !props.resource.readOnly || props.resource.name === 'applications' || props.resource.name === 'users';
}

function openGamePermissions(item: Record<string, any>): void {
    router.visit(
        userGamePermissions(item.id, {
            query: item.tenant_id ? { company_id: item.tenant_id } : undefined,
        }).url,
    );
}

function displayValue(item: Record<string, any>, column: string): string {
    const value = item[column];
    const field = props.resource.fields.find((field) => field.name === column);

    if (
        [
            'is_owner',
            'is_company_admin',
            'is_platform_admin',
            'is_system',
            'is_visible',
        ].includes(column)
    ) {
        return Boolean(value) ? '是' : '否';
    }

    if (field?.type === 'select') {
        const option = fieldOptions(field).find(
            (option) => optionValue(option) === String(value),
        );

        if (option) {
            return optionLabel(option);
        }
    }

    if (column === 'status') {
        return Boolean(value) ? '启用' : '停用';
    }

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

watch(
    () => [form.tenant_id, form.target_tenant_id],
    () => {
        if (props.resource.name !== 'users') {
            return;
        }

        const allowedRoleIds = new Set(
            fieldOptions({
                name: 'role_ids',
                label: '角色',
                type: 'multiselect',
            }).map(optionValue),
        );

        form.role_ids = (form.role_ids ?? [])
            .map(String)
            .filter((roleId: string) => allowedRoleIds.has(roleId));
    },
);

onUnmounted(removeFlashListener);
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
            <div class="font-medium">新的系统密钥只显示一次</div>
            <div class="mt-2 font-mono break-all">{{ rotatedSecret }}</div>
        </div>

        <div
            v-if="resource.name === 'menus'"
            class="rounded-lg border border-sidebar-border/70 p-4 text-sm text-muted-foreground"
        >
            菜单按父级菜单和排序字段组成树形结构。隐藏菜单只影响入口可见性，不替代服务端接口鉴权。
        </div>

        <div class="flex flex-wrap items-center gap-2">
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
            <Select
                v-if="(options.company_id ?? []).length > 0"
                v-model="companyId"
                @update:model-value="runSearch"
            >
                <SelectTrigger class="w-48">
                    <SelectValue placeholder="全部公司" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">全部公司</SelectItem>
                    <SelectItem
                        v-for="option in options.company_id"
                        :key="optionValue(option)"
                        :value="optionValue(option)"
                    >
                        {{ optionLabel(option) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button variant="secondary" @click="runSearch">搜索</Button>
        </div>

        <form
            v-if="showForm && !resource.readOnly"
            :class="[
                'grid gap-2 rounded-lg border border-sidebar-border/70 bg-card/40 p-3 md:grid-cols-2 dark:border-sidebar-border',
                formPanelClass,
            ]"
            @submit.prevent="submit"
        >
            <div
                v-for="field in visibleFields"
                :key="field.name"
                :class="fieldClass(field)"
            >
                <div class="flex items-center justify-between gap-2">
                    <Label :for="field.name" class="text-xs font-medium">{{
                        field.label
                    }}</Label>
                    <Button
                        v-if="field.generatePassword"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="h-7 px-2 text-xs"
                        data-test="generate-password"
                        @click="generateRandomPassword(field)"
                    >
                        随机生成密码
                    </Button>
                </div>

                <textarea
                    v-if="field.type === 'textarea'"
                    :id="field.name"
                    v-model="form[field.name]"
                    class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                />

                <div
                    v-else-if="field.type === 'checkbox'"
                    class="flex h-8 items-center gap-2"
                >
                    <Checkbox
                        :id="field.name"
                        :model-value="Boolean(form[field.name])"
                        @update:model-value="form[field.name] = $event"
                    />
                    <span class="text-sm text-muted-foreground">是</span>
                </div>

                <div v-else-if="field.type === 'select'" class="space-y-1.5">
                    <Select v-model="form[field.name]">
                        <SelectTrigger class="w-full">
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
                        v-if="field.description"
                        class="text-xs text-muted-foreground"
                    >
                        {{ field.description }}
                    </div>
                </div>

                <div
                    v-else-if="field.type === 'multiselect'"
                    class="max-h-28 min-h-16 space-y-1.5 overflow-y-auto rounded-md border border-sidebar-border/70 bg-background/60 p-2.5"
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
                    <div
                        v-if="fieldOptions(field).length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        暂无可选角色。
                    </div>
                </div>

                <div v-else class="space-y-1.5">
                    <Input
                        :id="field.name"
                        v-model="form[field.name]"
                        :type="field.type"
                    />
                    <div
                        v-if="field.description"
                        class="text-xs text-muted-foreground"
                    >
                        {{ field.description }}
                    </div>
                </div>

                <div
                    v-if="form.errors[field.name]"
                    class="text-sm text-red-600"
                >
                    {{ form.errors[field.name] }}
                </div>
            </div>

            <div
                class="flex gap-2 border-t border-sidebar-border/70 pt-2 md:col-span-2"
            >
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? '更新' : '创建' }}
                </Button>
                <Button type="button" variant="ghost" @click="showForm = false">
                    取消
                </Button>
            </div>
        </form>

        <div
            class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
        >
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
                        <th
                            v-if="shouldShowActions()"
                            class="w-32 px-3 py-2 font-medium"
                        >
                            操作
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items.data"
                        :key="item.row_key ?? item.id"
                        class="border-t border-sidebar-border/70"
                    >
                        <td
                            v-for="column in resource.columns"
                            :key="column"
                            class="px-3 py-2"
                        >
                            <Button
                                v-if="column === 'status' && !resource.readOnly"
                                type="button"
                                variant="outline"
                                :class="statusButtonClass(item, column)"
                                @click="toggleStatus(item)"
                            >
                                {{ displayValue(item, column) }}
                            </Button>
                            <Badge
                                v-else-if="column === 'status'"
                                variant="outline"
                                :class="statusBadgeClass(item, column)"
                            >
                                {{ displayValue(item, column) }}
                            </Badge>
                            <span v-else>{{ displayValue(item, column) }}</span>
                        </td>
                        <td v-if="shouldShowActions()" class="px-3 py-2">
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
                                        !resource.readOnly &&
                                        resource.actions?.includes(
                                            'rotateSecret',
                                        )
                                    "
                                    size="icon"
                                    variant="ghost"
                                    :aria-label="`轮换 ${item.name} 的客户端密钥`"
                                    :title="
                                        rotatingApplicationId === item.id
                                            ? '正在轮换客户端密钥'
                                            : '轮换客户端密钥'
                                    "
                                    :disabled="rotatingApplicationId !== null"
                                    @click="rotateSecret(item)"
                                >
                                    <KeyRound
                                        class="size-4"
                                        :class="{
                                            'animate-pulse':
                                                rotatingApplicationId ===
                                                item.id,
                                        }"
                                    />
                                </Button>
                                <Button
                                    v-if="resource.name === 'users' && !item.is_platform_admin"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="配置游戏权限"
                                    title="配置游戏权限"
                                    @click="openGamePermissions(item)"
                                >
                                    <ShieldCheck class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="items.data.length === 0">
                        <td
                            :colspan="
                                resource.columns.length +
                                (shouldShowActions() ? 1 : 0)
                            "
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
            >
                <span v-html="link.label" />
            </Button>
        </div>
    </div>
</template>
