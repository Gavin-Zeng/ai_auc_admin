<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AppWindow,
    CheckCircle2,
    ChevronLeft,
    CircleAlert,
    KeyRound,
    ListTree,
    LockKeyhole,
    ServerCog,
    ShieldCheck,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { openForTenant } from '@/actions/App/Http/Controllers/Admin/ApplicationController';
import { rotateSecret } from '@/routes/applications';
import { index as applicationsIndex } from '@/routes/applications';
import { index as menusIndex } from '@/routes/menus';
import { index as permissionsIndex } from '@/routes/permissions';
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

type Application = {
    id: number;
    name: string;
    client_id: string;
    base_url: string;
    redirect_uri: string;
    icon: string | null;
    status: string;
    secret_configured: boolean;
};

type Tenant = {
    id: number;
    code: string;
    name: string;
    status: string;
};

type Permission = {
    id: number;
    application_id: number | null;
    code: string;
    name: string;
    group: string | null;
    status: string;
    description: string | null;
};

type Menu = {
    id: number;
    parent_id: number | null;
    code: string;
    title: string;
    href: string | null;
    icon: string | null;
    required_permissions: string[];
    sort_order: number;
    is_visible: boolean;
    status: string;
    children: Menu[];
};

type Role = {
    id: number;
    code: string;
    name: string;
    status: string;
};

type Check = {
    label: string;
    passed: boolean;
    message: string;
};

type TenantApplication = {
    id: number;
    tenant_id: number;
    tenant_name: string | null;
    required_permissions: string[];
    status: string;
    sort_order: number;
};

type Option = {
    value: number | string;
    label: string;
};

const props = defineProps<{
    application: Application;
    tenant: Tenant;
    permissions: Permission[];
    menus: Menu[];
    flatMenus: Menu[];
    authorization: {
        required_permissions: string[];
        roles: Role[];
    };
    tenantApplications: TenantApplication[];
    tenantOptions: Option[];
    permissionOptions: Option[];
    canManageTenantApplications: boolean;
    checks: Check[];
}>();

const allTabs = [
    { key: 'overview', label: '基本信息', icon: AppWindow },
    { key: 'sso', label: 'SSO 配置', icon: ServerCog },
    { key: 'permissions', label: '权限点', icon: ShieldCheck },
    { key: 'menus', label: '菜单树', icon: ListTree },
    { key: 'authorization', label: '系统授权', icon: UsersRound },
    { key: 'companies', label: '公司开通', icon: UsersRound },
    { key: 'checks', label: '接入检查', icon: CheckCircle2 },
] as const;

type TabKey = (typeof allTabs)[number]['key'];

const activeTab = ref<TabKey>('overview');
const page = usePage();
const tabs = computed(() =>
    allTabs.filter(
        (tab) => tab.key !== 'companies' || props.canManageTenantApplications,
    ),
);
const rotatedSecret = computed(() => page.props.secret as string | undefined);
const passedChecks = computed(
    () => props.checks.filter((check) => check.passed).length,
);
const tenantApplicationForm = useForm({
    tenant_id: '',
    required_permissions: [] as string[],
    status: 'active',
    sort_order: 0,
});

function statusLabel(status: string): string {
    return status === 'active' ? '启用' : '停用';
}

function rotateApplicationSecret(): void {
    router.post(
        rotateSecret(props.application.id).url,
        {},
        { preserveScroll: true },
    );
}

function toggleTenantPermission(permission: string): void {
    const permissions = tenantApplicationForm.required_permissions.map(String);

    tenantApplicationForm.required_permissions = permissions.includes(
        permission,
    )
        ? permissions.filter((item) => item !== permission)
        : [...permissions, permission];
}

function editTenantApplication(tenantApplication: TenantApplication): void {
    tenantApplicationForm.tenant_id = String(tenantApplication.tenant_id);
    tenantApplicationForm.required_permissions = [
        ...tenantApplication.required_permissions,
    ];
    tenantApplicationForm.status = tenantApplication.status;
    tenantApplicationForm.sort_order = tenantApplication.sort_order;
}

function submitTenantApplication(): void {
    tenantApplicationForm.post(openForTenant(props.application.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${application.name} - 系统详情`" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="applicationsIndex().url">
                        <ChevronLeft class="size-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold tracking-normal">
                            {{ application.name }}
                        </h1>
                        <Badge variant="secondary">
                            {{ statusLabel(application.status) }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ tenant.name }} / {{ application.client_id }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="secondary" as-child>
                    <Link :href="permissionsIndex().url">维护权限</Link>
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="menusIndex().url">维护菜单</Link>
                </Button>
                <Button
                    v-if="canManageTenantApplications"
                    @click="rotateApplicationSecret"
                >
                    <KeyRound class="size-4" />
                    轮换密钥
                </Button>
            </div>
        </div>

        <div
            v-if="rotatedSecret"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <div class="font-medium">新的系统密钥只显示一次</div>
            <div class="mt-2 font-mono break-all">{{ rotatedSecret }}</div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="text-sm text-muted-foreground">权限点</div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ permissions.length }}
                </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="text-sm text-muted-foreground">菜单</div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ flatMenus.length }}
                </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="text-sm text-muted-foreground">已开通公司</div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ tenantApplications.length }}
                </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="text-sm text-muted-foreground">接入检查</div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ passedChecks }}/{{ checks.length }}
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-sidebar-border/70">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm"
                :class="
                    activeTab === tab.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                type="button"
                @click="activeTab = tab.key"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
            </button>
        </div>

        <section
            v-if="activeTab === 'overview'"
            class="grid gap-4 lg:grid-cols-2"
        >
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h2 class="font-medium">系统信息</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="grid gap-1">
                        <dt class="text-muted-foreground">客户端 ID</dt>
                        <dd class="font-mono">{{ application.client_id }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-muted-foreground">基础地址</dt>
                        <dd class="break-all">{{ application.base_url }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-muted-foreground">回调地址</dt>
                        <dd class="break-all">
                            {{ application.redirect_uri }}
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h2 class="font-medium">公司上下文</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="grid gap-1">
                        <dt class="text-muted-foreground">公司名称</dt>
                        <dd>{{ tenant.name }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-muted-foreground">公司编码</dt>
                        <dd class="font-mono">{{ tenant.code }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-muted-foreground">状态</dt>
                        <dd>{{ statusLabel(tenant.status) }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section v-else-if="activeTab === 'sso'" class="grid gap-4">
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h2 class="font-medium">SSO 配置</h2>
                <div class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                    <div>
                        <div class="text-muted-foreground">client_id</div>
                        <div class="mt-1 font-mono break-all">
                            {{ application.client_id }}
                        </div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">secret</div>
                        <div class="mt-1 flex items-center gap-2">
                            <LockKeyhole class="size-4 text-muted-foreground" />
                            {{
                                application.secret_configured
                                    ? '已配置，明文不可查看'
                                    : '未配置'
                            }}
                        </div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">authorize</div>
                        <div class="mt-1 font-mono">GET /sso/authorize</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">token exchange</div>
                        <div class="mt-1 font-mono">POST /sso/token</div>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="activeTab === 'permissions'" class="grid gap-3">
            <div
                v-for="permission in permissions"
                :key="permission.id"
                class="rounded-lg border border-sidebar-border/70 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-mono text-sm">
                            {{ permission.code }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ permission.name }}
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {{ statusLabel(permission.status) }}
                    </Badge>
                </div>
                <div class="mt-3 text-sm text-muted-foreground">
                    {{ permission.group ?? '未分组' }}
                </div>
            </div>
            <div
                v-if="permissions.length === 0"
                class="rounded-lg border border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground"
            >
                当前系统还没有绑定权限点。
            </div>
        </section>

        <section v-else-if="activeTab === 'menus'" class="grid gap-3">
            <template v-if="menus.length > 0">
                <div
                    v-for="menu in menus"
                    :key="menu.id"
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <div class="font-medium">{{ menu.title }}</div>
                            <div
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ menu.code }} / {{ menu.href ?? '-' }}
                            </div>
                        </div>
                        <Badge variant="secondary">
                            {{ menu.is_visible ? '显示' : '隐藏' }}
                        </Badge>
                    </div>
                    <div
                        v-if="menu.required_permissions.length > 0"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <Badge
                            v-for="permission in menu.required_permissions"
                            :key="permission"
                            variant="outline"
                        >
                            {{ permission }}
                        </Badge>
                    </div>
                    <div
                        v-if="menu.children.length > 0"
                        class="mt-4 grid gap-2 border-l border-sidebar-border/70 pl-4"
                    >
                        <div
                            v-for="child in menu.children"
                            :key="child.id"
                            class="rounded-md bg-muted/40 p-3"
                        >
                            <div class="font-medium">{{ child.title }}</div>
                            <div
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ child.code }} / {{ child.href ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <div
                v-else
                class="rounded-lg border border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground"
            >
                当前系统还没有配置菜单树。
            </div>
        </section>

        <section v-else-if="activeTab === 'authorization'" class="grid gap-4">
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h2 class="font-medium">系统访问条件</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Badge
                        v-for="permission in authorization.required_permissions"
                        :key="permission"
                        variant="outline"
                    >
                        {{ permission }}
                    </Badge>
                    <span
                        v-if="authorization.required_permissions.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        未设置系统访问权限，当前公司成员可见。
                    </span>
                </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h2 class="font-medium">命中角色</h2>
                <div class="mt-3 grid gap-2">
                    <div
                        v-for="role in authorization.roles"
                        :key="role.id"
                        class="flex items-center justify-between rounded-md bg-muted/40 p-3 text-sm"
                    >
                        <span>{{ role.name }}</span>
                        <span class="font-mono text-muted-foreground">
                            {{ role.code }}
                        </span>
                    </div>
                    <div
                        v-if="authorization.roles.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        暂无角色命中当前系统访问条件。
                    </div>
                </div>
            </div>
        </section>

        <section
            v-else-if="activeTab === 'companies'"
            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]"
        >
            <div class="rounded-lg border border-sidebar-border/70">
                <div class="border-b border-sidebar-border/70 px-4 py-3">
                    <h2 class="font-medium">已开通公司</h2>
                </div>
                <div class="divide-y divide-sidebar-border/70">
                    <div
                        v-for="tenantApplication in tenantApplications"
                        :key="tenantApplication.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div>
                            <div class="font-medium">
                                {{ tenantApplication.tenant_name ?? '-' }}
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-for="permission in tenantApplication.required_permissions"
                                    :key="permission"
                                    variant="outline"
                                >
                                    {{ permission }}
                                </Badge>
                                <span
                                    v-if="
                                        tenantApplication.required_permissions
                                            .length === 0
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    无入口权限限制
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="secondary">
                                {{ statusLabel(tenantApplication.status) }}
                            </Badge>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="
                                    editTenantApplication(tenantApplication)
                                "
                            >
                                编辑
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="tenantApplications.length === 0"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        当前系统还没有开通给任何公司。
                    </div>
                </div>
            </div>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 p-4"
                @submit.prevent="submitTenantApplication"
            >
                <h2 class="font-medium">开通配置</h2>
                <div class="grid gap-1.5">
                    <Label for="tenant_id">公司</Label>
                    <Select v-model="tenantApplicationForm.tenant_id">
                        <SelectTrigger>
                            <SelectValue placeholder="选择公司" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in tenantOptions"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div
                        v-if="tenantApplicationForm.errors.tenant_id"
                        class="text-sm text-red-600"
                    >
                        {{ tenantApplicationForm.errors.tenant_id }}
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="status">状态</Label>
                    <Select v-model="tenantApplicationForm.status">
                        <SelectTrigger>
                            <SelectValue placeholder="状态" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">启用</SelectItem>
                            <SelectItem value="disabled">停用</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <Label for="sort_order">排序</Label>
                    <Input
                        id="sort_order"
                        v-model="tenantApplicationForm.sort_order"
                        type="number"
                        min="0"
                    />
                </div>

                <div class="grid gap-1.5">
                    <Label>入口权限</Label>
                    <div
                        class="max-h-44 space-y-2 overflow-y-auto rounded-md border border-sidebar-border/70 p-2"
                    >
                        <label
                            v-for="option in permissionOptions"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="
                                    tenantApplicationForm.required_permissions
                                        .map(String)
                                        .includes(String(option.value))
                                "
                                @update:model-value="
                                    toggleTenantPermission(String(option.value))
                                "
                            />
                            <span>{{ option.label }}</span>
                        </label>
                    </div>
                </div>

                <Button
                    type="submit"
                    :disabled="tenantApplicationForm.processing"
                >
                    保存开通配置
                </Button>
            </form>
        </section>

        <section v-else class="grid gap-3">
            <div
                v-for="check in checks"
                :key="check.label"
                class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-4"
            >
                <CheckCircle2
                    v-if="check.passed"
                    class="mt-0.5 size-5 text-emerald-600"
                />
                <CircleAlert v-else class="mt-0.5 size-5 text-amber-600" />
                <div>
                    <div class="font-medium">{{ check.label }}</div>
                    <div class="text-sm text-muted-foreground">
                        {{ check.message }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
