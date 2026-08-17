<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import {
    KeyRound,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import AppEmptyState from '@/components/app/AppEmptyState.vue';
import AppPageHeader from '@/components/app/AppPageHeader.vue';
import AppPagination from '@/components/app/AppPagination.vue';
import AppStatusTag from '@/components/app/AppStatusTag.vue';
import { formatDate, formatDateTime, formatNumber } from '@/lib/formatters';
import { resourceColumn } from '@/lib/resourceColumns';
import { resourceRoutes } from '@/lib/resourceRoutes';
import type { ResourceName } from '@/lib/resourceRoutes';
import { rotateSecret as rotateApplicationSecret } from '@/routes/applications';
import { index as userGamePermissions } from '@/routes/users/game-permissions';

type FieldOption =
    | number
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
    platformOnly?: boolean;
    generatePassword?: boolean;
};

type ResourceConfig = {
    name: ResourceName;
    label: string;
    description?: string;
    createLabel?: string;
    currentTenantId?: number | string | null;
    readOnly?: boolean;
    fields: FieldConfig[];
    columns: string[];
    actions?: string[];
};

type ResourceItem = Record<string, unknown> & {
    id?: number | string;
    row_key?: number | string;
    name?: string;
    status?: boolean | number | string;
    tenant_id?: number | string | null;
    is_platform_admin?: boolean;
};

type PaginatedItems = {
    data: ResourceItem[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
};

const props = defineProps<{
    resource: ResourceConfig;
    items: PaginatedItems;
    filters: { search?: string; company_id?: number | string | null };
    options: Record<string, FieldOption[]>;
}>();

const page = usePage();
const editing = ref<ResourceItem | null>(null);
const showForm = ref(false);
const search = ref(props.filters.search ?? '');
const companyId = ref(
    props.filters.company_id ? String(props.filters.company_id) : 'all',
);
const rotatedSecret = ref('');
const secretDialogVisible = ref(false);
const rotatingApplicationId = ref<number | string | null>(null);
const loading = ref(false);
const loadError = ref('');

const routes = computed(() => resourceRoutes[props.resource.name]);
const visibleFields = computed(() =>
    props.resource.fields.filter(
        (field) =>
            !(
                field.platformOnly &&
                !page.props.auth.identity?.is_platform_admin
            ) && (editing.value ? !field.createOnly : !field.updateOnly),
    ),
);
const blankValues = computed(() =>
    Object.fromEntries(
        props.resource.fields.map((field) => [
            field.name,
            field.type === 'checkbox'
                ? Boolean(field.default)
                : field.type === 'multiselect'
                  ? []
                  : field.default !== undefined && field.default !== null
                    ? String(field.default)
                    : '',
        ]),
    ),
);
const form = useForm<Record<string, any>>({ ...blankValues.value });

const removeFlashListener = router.on('flash', (event) => {
    const secret = (event as CustomEvent).detail?.flash?.secret;

    if (typeof secret === 'string') {
        rotatedSecret.value = secret;
        secretDialogVisible.value = true;
    }
});
const removeStartListener = router.on('start', () => {
    loading.value = true;
    loadError.value = '';
});
const removeFinishListener = router.on('finish', () => {
    loading.value = false;
});
const removeNetworkErrorListener = router.on('networkError', () => {
    loadError.value = '数据加载失败，请检查网络后重试。';
});

const valueLabels: Record<string, string> = {
    active: '启用',
    disabled: '停用',
    '1': '启用',
    '0': '停用',
};

function optionValue(option: FieldOption): string {
    return typeof option === 'object' ? String(option.value) : String(option);
}

function optionLabel(option: FieldOption): string {
    if (typeof option === 'object') {
        return option.label;
    }

    return valueLabels[String(option)] ?? String(option);
}

function fieldOptions(field: FieldConfig): FieldOption[] {
    const available = field.options ?? props.options[field.name] ?? [];

    if (
        props.resource.name === 'users' &&
        field.name === 'role_ids' &&
        (form.tenant_id || form.target_tenant_id)
    ) {
        return available.filter(
            (option) =>
                typeof option !== 'object' ||
                String(option.tenant_id ?? '') ===
                    String(form.tenant_id || form.target_tenant_id),
        );
    }

    if (
        props.resource.name === 'menus' &&
        field.name === 'parent_id' &&
        form.tenant_id
    ) {
        return available.filter(
            (option) =>
                typeof option !== 'object' ||
                String(option.tenant_id ?? '') === String(form.tenant_id),
        );
    }

    return available;
}

function generateRandomPassword(field: FieldConfig): void {
    const characters =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    const values = new Uint32Array(16);
    crypto.getRandomValues(values);
    form[field.name] = Array.from(
        values,
        (value) => characters[value % characters.length],
    ).join('');
}

function requestPayload(): Record<string, any> {
    const fieldNames = new Set(visibleFields.value.map((field) => field.name));

    return Object.fromEntries(
        Object.entries(form.data()).filter(([key]) => fieldNames.has(key)),
    );
}

function startCreate(): void {
    editing.value = null;
    form.defaults({ ...blankValues.value });
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function startEdit(item: ResourceItem): void {
    editing.value = item;
    const values: Record<string, any> = { ...blankValues.value };

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

function submit(): void {
    const submitUrl = editing.value?.id
        ? routes.value.update?.(editing.value.id)
        : routes.value.store?.();

    if (!submitUrl) {
        return;
    }

    form.transform(() => requestPayload());
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
        },
        onFinish: () => form.transform((data) => data),
    };

    if (editing.value) {
        form.put(submitUrl, options);
    } else {
        form.post(submitUrl, options);
    }
}

function statusPayload(
    item: ResourceItem,
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

async function toggleStatus(item: ResourceItem): Promise<void> {
    if (props.resource.readOnly || !item.id || !routes.value.update) {
        return;
    }

    const nextStatus = !Boolean(item.status);

    try {
        await ElMessageBox.confirm(
            nextStatus ? '确定启用该记录吗？' : '确定停用该记录吗？',
            '确认状态变更',
            {
                type: 'warning',
                confirmButtonText: '确认',
                cancelButtonText: '取消',
            },
        );
        router.put(
            routes.value.update(item.id),
            statusPayload(item, nextStatus),
            { preserveScroll: true },
        );
    } catch {
        // Cancellation does not require feedback.
    }
}

async function rotateSecret(item: ResourceItem): Promise<void> {
    if (!item.id) {
        return;
    }

    try {
        await ElMessageBox.confirm(
            `确定轮换“${item.name ?? ''}”的客户端密钥吗？旧密钥将立即失效。`,
            '轮换客户端密钥',
            {
                type: 'warning',
                confirmButtonText: '确认轮换',
                cancelButtonText: '取消',
            },
        );
    } catch {
        return;
    }

    rotatedSecret.value = '';
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

function runSearch(): void {
    router.get(
        routes.value.index({
            search: search.value || undefined,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetSearch(): void {
    search.value = '';
    companyId.value = 'all';
    runSearch();
}

function goToPage(currentPage: number): void {
    router.get(
        routes.value.index({
            page: currentPage,
            search: search.value || undefined,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function openGamePermissions(item: ResourceItem): void {
    if (!item.id) {
        return;
    }

    router.visit(
        userGamePermissions(item.id, {
            query: item.tenant_id ? { company_id: item.tenant_id } : undefined,
        }).url,
    );
}

function displayValue(item: ResourceItem, column: string): string {
    const value = item[column];
    const meta = resourceColumn(props.resource.name, column);
    const field = props.resource.fields.find(
        (candidate) => candidate.name === column,
    );

    if (meta.kind === 'datetime') {
        return formatDateTime(value);
    }

    if (meta.kind === 'date') {
        return formatDate(value);
    }

    if (meta.kind === 'number') {
        return formatNumber(value);
    }

    if (meta.kind === 'boolean') {
        return Boolean(value) ? '是' : '否';
    }

    if (field?.type === 'select') {
        const option = fieldOptions(field).find(
            (candidate) => optionValue(candidate) === String(value),
        );

        if (option) {
            return optionLabel(option);
        }
    }

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return valueLabels[String(value)] ?? String(value);
}

function handleAction(command: string, item: ResourceItem): void {
    if (command === 'status') {
        void toggleStatus(item);
    } else if (command === 'secret') {
        void rotateSecret(item);
    } else if (command === 'permissions') {
        openGamePermissions(item);
    }
}

function shouldShowActions(): boolean {
    return !props.resource.readOnly || props.resource.name === 'users';
}

function beforeDrawerClose(done: () => void): void {
    if (form.processing) {
        return;
    }

    if (!form.isDirty) {
        done();

        return;
    }

    ElMessageBox.confirm('表单尚未保存，确定关闭吗？', '放弃更改', {
        type: 'warning',
        confirmButtonText: '放弃更改',
        cancelButtonText: '继续编辑',
    })
        .then(done)
        .catch(() => undefined);
}

function copySecret(): void {
    void window.navigator.clipboard.writeText(rotatedSecret.value);
}

watch(
    () => [form.tenant_id, form.target_tenant_id],
    () => {
        if (props.resource.name !== 'users') {
            return;
        }

        const roleField: FieldConfig = {
            name: 'role_ids',
            label: '角色',
            type: 'multiselect',
        };
        const allowedRoleIds = new Set(
            fieldOptions(roleField).map(optionValue),
        );
        form.role_ids = (form.role_ids ?? [])
            .map(String)
            .filter((id: string) => allowedRoleIds.has(id));
    },
);

onUnmounted(() => {
    removeFlashListener();
    removeStartListener();
    removeFinishListener();
    removeNetworkErrorListener();
});
</script>

<template>
    <Head :title="resource.label" />

    <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4 md:p-6">
        <AppPageHeader
            :title="resource.label"
            :description="resource.description"
        >
            <template v-if="!resource.readOnly" #actions>
                <ElButton type="primary" @click="startCreate">
                    <Plus class="size-4" />
                    {{ resource.createLabel ?? '新增' }}
                </ElButton>
            </template>
        </AppPageHeader>

        <ElAlert
            v-if="resource.name === 'menus'"
            type="info"
            :closable="false"
            title="菜单按父级菜单和排序字段组成树形结构；隐藏仅影响入口可见性，不替代服务端鉴权。"
        />

        <section
            class="flex flex-wrap items-center gap-2"
            aria-label="筛选条件"
        >
            <ElInput
                v-model="search"
                clearable
                placeholder="搜索"
                class="max-w-sm"
                @keyup.enter="runSearch"
            >
                <template #prefix><Search class="size-4" /></template>
            </ElInput>
            <ElSelect
                v-if="(options.company_id ?? []).length"
                v-model="companyId"
                class="w-48"
                @change="runSearch"
            >
                <ElOption label="全部公司" value="all" />
                <ElOption
                    v-for="option in options.company_id"
                    :key="optionValue(option)"
                    :label="optionLabel(option)"
                    :value="optionValue(option)"
                />
            </ElSelect>
            <ElButton type="primary" plain @click="runSearch">搜索</ElButton>
            <ElButton @click="resetSearch">重置</ElButton>
        </section>

        <ElAlert
            v-if="loadError"
            type="error"
            show-icon
            :closable="false"
            :title="loadError"
        >
            <ElButton size="small" @click="runSearch">重试</ElButton>
        </ElAlert>

        <ElTable
            v-loading="loading"
            :data="items.data"
            :row-key="
                (row: ResourceItem) => String(row.row_key ?? row.id ?? '')
            "
            class="app-table w-full"
            table-layout="fixed"
        >
            <template #empty>
                <AppEmptyState description="暂无符合条件的数据">
                    <ElButton
                        v-if="search || companyId !== 'all'"
                        @click="resetSearch"
                        >清除筛选</ElButton
                    >
                </AppEmptyState>
            </template>

            <ElTableColumn
                v-for="column in resource.columns"
                :key="column"
                :label="resourceColumn(resource.name, column).label"
                :align="resourceColumn(resource.name, column).align"
                :width="resourceColumn(resource.name, column).width"
                :min-width="resourceColumn(resource.name, column).minWidth"
            >
                <template #default="{ row }">
                    <AppStatusTag
                        v-if="
                            resourceColumn(resource.name, column).kind ===
                            'status'
                        "
                        :value="row[column]"
                    />
                    <ElTag
                        v-else-if="
                            resourceColumn(resource.name, column).kind ===
                            'boolean'
                        "
                        :type="row[column] ? 'success' : 'info'"
                        effect="plain"
                        size="small"
                    >
                        {{ displayValue(row, column) }}
                    </ElTag>
                    <ElTooltip
                        v-else-if="
                            resourceColumn(resource.name, column).tooltip
                        "
                        :content="displayValue(row, column)"
                        placement="top"
                    >
                        <span class="block truncate">{{
                            displayValue(row, column)
                        }}</span>
                    </ElTooltip>
                    <span
                        v-else
                        :class="{
                            'text-muted-foreground': resourceColumn(
                                resource.name,
                                column,
                            ).muted,
                        }"
                    >
                        {{ displayValue(row, column) }}
                    </span>
                </template>
            </ElTableColumn>

            <ElTableColumn
                v-if="shouldShowActions()"
                label="操作"
                width="150"
                fixed="right"
                align="right"
            >
                <template #default="{ row }">
                    <ElButton
                        v-if="!resource.readOnly"
                        link
                        type="primary"
                        @click="startEdit(row)"
                    >
                        <Pencil class="size-4" />
                        编辑
                    </ElButton>
                    <ElDropdown
                        trigger="click"
                        @command="
                            (command: string) => handleAction(command, row)
                        "
                    >
                        <ElButton text circle aria-label="更多操作"
                            ><MoreHorizontal class="size-4"
                        /></ElButton>
                        <template #dropdown>
                            <ElDropdownMenu>
                                <ElDropdownItem
                                    v-if="!resource.readOnly"
                                    command="status"
                                >
                                    {{ row.status ? '停用' : '启用' }}
                                </ElDropdownItem>
                                <ElDropdownItem
                                    v-if="
                                        resource.actions?.includes(
                                            'rotateSecret',
                                        )
                                    "
                                    command="secret"
                                    :disabled="rotatingApplicationId !== null"
                                >
                                    <KeyRound class="mr-2 size-4" />轮换密钥
                                </ElDropdownItem>
                                <ElDropdownItem
                                    v-if="
                                        resource.name === 'users' &&
                                        !row.is_platform_admin
                                    "
                                    command="permissions"
                                >
                                    <ShieldCheck class="mr-2 size-4" />游戏权限
                                </ElDropdownItem>
                            </ElDropdownMenu>
                        </template>
                    </ElDropdown>
                </template>
            </ElTableColumn>
        </ElTable>

        <AppPagination
            :current-page="items.current_page ?? 1"
            :last-page="items.last_page ?? 1"
            :page-size="items.per_page ?? Math.max(items.data.length, 1)"
            :total="items.total ?? items.data.length"
            :disabled="loading"
            @change="goToPage"
        />
    </div>

    <ElDrawer
        v-model="showForm"
        :title="
            editing
                ? `编辑${resource.label}`
                : (resource.createLabel ?? `新增${resource.label}`)
        "
        class="app-form-drawer"
        :before-close="beforeDrawerClose"
        destroy-on-close
    >
        <ElForm label-position="top" @submit.prevent="submit">
            <div class="grid gap-x-4 md:grid-cols-2">
                <ElFormItem
                    v-for="field in visibleFields"
                    :key="field.name"
                    :label="field.label"
                    :required="field.required"
                    :error="form.errors[field.name]"
                    :class="{ 'md:col-span-2': field.span === 2 }"
                >
                    <div class="w-full">
                        <ElInput
                            v-if="field.type === 'textarea'"
                            v-model="form[field.name]"
                            type="textarea"
                            :rows="4"
                        />
                        <ElCheckbox
                            v-else-if="field.type === 'checkbox'"
                            v-model="form[field.name]"
                            >是</ElCheckbox
                        >
                        <ElSelect
                            v-else-if="field.type === 'select'"
                            v-model="form[field.name]"
                            class="w-full"
                            clearable
                        >
                            <ElOption
                                v-for="option in fieldOptions(field)"
                                :key="optionValue(option)"
                                :label="optionLabel(option)"
                                :value="optionValue(option)"
                            />
                        </ElSelect>
                        <ElSelect
                            v-else-if="field.type === 'multiselect'"
                            v-model="form[field.name]"
                            class="w-full"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                        >
                            <ElOption
                                v-for="option in fieldOptions(field)"
                                :key="optionValue(option)"
                                :label="optionLabel(option)"
                                :value="optionValue(option)"
                            />
                        </ElSelect>
                        <ElInputNumber
                            v-else-if="field.type === 'number'"
                            v-model="form[field.name]"
                            class="w-full"
                            controls-position="right"
                        />
                        <ElInput
                            v-else
                            v-model="form[field.name]"
                            :type="field.type"
                            :show-password="field.type === 'password'"
                        >
                            <template v-if="field.generatePassword" #append>
                                <ElButton
                                    data-test="generate-password"
                                    @click="generateRandomPassword(field)"
                                >
                                    随机生成
                                </ElButton>
                            </template>
                        </ElInput>
                        <p
                            v-if="field.description"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ field.description }}
                        </p>
                    </div>
                </ElFormItem>
            </div>
        </ElForm>
        <template #footer>
            <ElButton :disabled="form.processing" @click="showForm = false"
                >取消</ElButton
            >
            <ElButton type="primary" :loading="form.processing" @click="submit">
                {{ editing ? '更新' : '创建' }}
            </ElButton>
        </template>
    </ElDrawer>

    <ElDialog
        v-model="secretDialogVisible"
        title="新的系统密钥"
        width="min(520px, 92vw)"
    >
        <ElAlert
            type="warning"
            :closable="false"
            title="密钥只显示一次，请立即保存。"
        />
        <ElInput :model-value="rotatedSecret" readonly class="mt-4 font-mono">
            <template #append>
                <ElButton @click="copySecret">复制</ElButton>
            </template>
        </ElInput>
        <template #footer>
            <ElButton type="primary" @click="secretDialogVisible = false"
                >我已保存</ElButton
            >
        </template>
    </ElDialog>
</template>
