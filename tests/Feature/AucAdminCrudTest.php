<?php

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

test('platform admin can create and disable a tenant', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->post(route('tenants.store'), [
            'code' => 'global',
            'name' => 'Global Tenant',
            'domain' => 'global.example.test',
            'status' => 'active',
        ])
        ->assertRedirect();

    $tenant = Tenant::query()->where('code', 'global')->firstOrFail();
    expect($tenant->status)->toBe('active');

    $this->delete(route('tenants.destroy', $tenant))->assertRedirect();
    expect($tenant->refresh()->status)->toBe('disabled');
});

test('tenant create form hides status and defaults to active', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get(route('tenants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.label', '公司管理')
            ->where('resource.fields.3.updateOnly', true)
            ->where('resource.fields.3.default', 'active'));

    $this->actingAs($admin)
        ->post(route('tenants.store'), [
            'code' => 'hidden-status',
            'name' => 'Hidden Status',
            'domain' => 'hidden-status.example.test',
        ])
        ->assertRedirect();

    expect(Tenant::query()->where('code', 'hidden-status')->value('status'))->toBe('active');
});

test('company admin cannot promote members to company owner', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    aucGrant($admin, $tenant, ['users.manage']);
    aucGrant($member, $tenant, []);

    $this->actingAs($admin)
        ->put(route('users.update', $member), [
            'account' => $member->account,
            'name' => $member->name,
            'email' => $member->email,
            'status' => 'active',
            'is_owner' => true,
            'role_ids' => [],
        ])
        ->assertForbidden();
});

test('company admin cannot disable a company owner membership', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $owner = User::factory()->create();
    aucGrant($admin, $tenant, ['users.manage']);
    aucGrant($owner, $tenant, [], isOwner: true);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $owner))
        ->assertForbidden();
});

test('platform admin can disable a company owner membership', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create();
    $owner = User::factory()->create();
    aucGrant($owner, $tenant, [], isOwner: true);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $owner))
        ->assertRedirect();

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $owner->id)
        ->value('status'))->toBe('disabled');
});

test('company member list shows roles company admin flag and status before actions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    aucGrant($admin, $tenant, ['users.manage'], isOwner: true);
    aucGrant($member, $tenant, [], isOwner: true);

    $role = Role::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'operator')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.createLabel', '新增账号')
            ->where('resource.columns', ['account', 'name', 'email', 'company_names', 'role_names', 'is_owner', 'is_platform_admin', 'status'])
            ->where('resource.fields.5.label', '公司超管')
            ->missing('resource.fields.tenant_id')
            ->where('items.data.0.company_names.0', $tenant->name)
            ->where('items.data.0.role_names.0', "{$role->code} - {$role->name}")
            ->where('items.data.0.is_owner', 1)
            ->where('items.data.0.status', 'active'));
});

test('platform admin user form exposes company selector and company scoped role options', function () {
    $tenant = Tenant::factory()->create(['name' => 'A 公司', 'status' => 'disabled']);
    $otherTenant = Tenant::factory()->create(['name' => 'B 公司', 'status' => 'active']);
    $admin = User::factory()->platformAdmin()->create();
    Role::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'alpha',
        'name' => 'Alpha',
    ]);
    Role::factory()->create([
        'tenant_id' => $otherTenant->id,
        'code' => 'beta',
        'name' => 'Beta',
    ]);

    $this->actingAs($admin)
        ->get(route('users.index', ['tenant_id' => $tenant->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.createLabel', '新增账号')
            ->where('resource.fields.4.name', 'tenant_id')
            ->where('resource.fields.4.label', '所属公司')
            ->where('resource.fields.4.createOnly', true)
            ->missing('resource.fields.4.default')
            ->where('resource.fields.4.group', '公司与状态')
            ->where('resource.fields.7.group', '角色授权')
            ->where('options.tenant_id.0.value', (string) $tenant->id)
            ->where('options.tenant_id.0.label', 'A 公司（已停用）')
            ->where('options.tenant_id.1.label', 'B 公司')
            ->where('options.role_ids.0.tenant_id', (string) $tenant->id)
            ->where('options.role_ids.1.tenant_id', (string) $otherTenant->id));
});

test('platform admin can create account for selected company with active membership by default', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create();
    $role = Role::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($admin)
        ->post(route('users.store', ['tenant_id' => $otherTenant->id]), [
            'account' => 'NewAccount',
            'name' => 'New Account',
            'email' => 'new-account@example.test',
            'password' => 'password123',
            'tenant_id' => $otherTenant->id,
            'is_owner' => false,
            'role_ids' => [$role->id],
        ])
        ->assertRedirect();

    $user = User::query()->where('account', 'NewAccount')->firstOrFail();

    expect(TenantUser::query()
        ->where('tenant_id', $otherTenant->id)
        ->where('user_id', $user->id)
        ->value('status'))->toBe('active');
    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
    expect($user->roles()->wherePivot('tenant_id', $otherTenant->id)->pluck('auc_roles.id')->all())->toBe([$role->id]);
});

test('company admin can toggle member status through update payload', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    aucGrant($admin, $tenant, ['users.manage'], isOwner: true);
    aucGrant($member, $tenant, []);

    $this->actingAs($admin)
        ->put(route('users.update', $member), [
            'account' => $member->account,
            'name' => $member->name,
            'email' => $member->email,
            'password' => '',
            'status' => 'disabled',
            'is_owner' => false,
            'role_ids' => [],
        ])
        ->assertRedirect();

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $member->id)
        ->value('status'))->toBe('disabled');

    $this->put(route('users.update', $member), [
        'account' => $member->account,
        'name' => $member->name,
        'email' => $member->email,
        'password' => '',
        'status' => 'active',
        'is_owner' => false,
        'role_ids' => [],
    ])->assertRedirect();

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $member->id)
        ->value('status'))->toBe('active');
});

test('company admin cannot spoof another company when creating account', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['users.manage']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'account' => 'Spoofed',
            'name' => 'Spoofed User',
            'email' => 'spoofed@example.test',
            'password' => 'password123',
            'tenant_id' => $otherTenant->id,
            'is_owner' => false,
            'role_ids' => [],
        ])
        ->assertForbidden();

    expect(User::query()->where('account', 'Spoofed')->exists())->toBeFalse();
});

test('platform admin cannot assign role outside selected company when creating account', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create();
    $foreignRole = Role::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->post(route('users.store', ['tenant_id' => $otherTenant->id]), [
            'account' => 'ForeignRole',
            'name' => 'Foreign Role',
            'email' => 'foreign-role@example.test',
            'password' => 'password123',
            'tenant_id' => $otherTenant->id,
            'is_owner' => false,
            'role_ids' => [$foreignRole->id],
        ])
        ->assertSessionHasErrors('role_ids.0');

    expect(User::query()->where('account', 'ForeignRole')->exists())->toBeFalse();
});

test('tenant admin can manage roles and permission version changes', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['roles.manage']);

    $permission = Permission::factory()->create(['code' => 'reports.view']);
    $before = TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version');

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'code' => 'analyst',
            'name' => 'Analyst',
            'status' => 'active',
            'permission_ids' => [$permission->id],
        ])
        ->assertRedirect();

    $role = Role::query()->where('tenant_id', $tenant->id)->where('code', 'analyst')->firstOrFail();

    expect($role->permissions()->pluck('auc_permissions.code')->all())->toBe(['reports.view']);
    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version'))->toBeGreaterThan($before);
});

test('role create form exposes company selector only for platform admin and hides status', function () {
    $tenant = Tenant::factory()->create(['name' => 'A 公司', 'status' => 'disabled']);
    $admin = User::factory()->platformAdmin()->create();
    Role::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('roles.index', ['tenant_id' => $tenant->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.createLabel', '新增角色')
            ->where('resource.fields.2.name', 'tenant_id')
            ->where('resource.fields.2.label', '所属公司')
            ->where('resource.fields.2.createOnly', true)
            ->where('resource.fields.2.group', '公司归属')
            ->where('resource.fields.3.name', 'status')
            ->where('resource.fields.3.updateOnly', true)
            ->where('resource.fields.3.default', 'active')
            ->where('options.tenant_id.0.label', 'A 公司（已停用）'));

    $user = User::factory()->create();
    aucGrant($user, $tenant, ['roles.manage']);

    $this->actingAs($user)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.fields.0.name', 'code')
            ->where('resource.fields.1.name', 'name')
            ->where('resource.fields.2.name', 'status')
            ->where('resource.fields.2.updateOnly', true)
            ->where('resource.fields.2.default', 'active')
            ->where('resource.fields.3.name', 'permission_ids'));
});

test('platform admin can create role for selected company and company admin cannot spoof company', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $platformAdmin = User::factory()->platformAdmin()->create();
    $companyAdmin = User::factory()->create();
    aucGrant($companyAdmin, $tenant, ['roles.manage']);
    $permission = Permission::factory()->create(['status' => 'active']);

    $this->actingAs($platformAdmin)
        ->post(route('roles.store', ['tenant_id' => $otherTenant->id]), [
            'tenant_id' => $otherTenant->id,
            'code' => 'planner',
            'name' => 'Planner',
            'permission_ids' => [$permission->id],
        ])
        ->assertRedirect();

    expect(Role::query()->where('tenant_id', $otherTenant->id)->where('code', 'planner')->exists())->toBeTrue();

    $this->actingAs($companyAdmin)
        ->post(route('roles.store'), [
            'tenant_id' => $otherTenant->id,
            'code' => 'spoofed',
            'name' => 'Spoofed',
            'permission_ids' => [$permission->id],
        ])
        ->assertForbidden();

    expect(Role::query()->where('code', 'spoofed')->exists())->toBeFalse();
});

test('resources create forms do not expose status and still save active by default', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->platformAdmin()->create();
    aucGrant($admin, $tenant, ['applications.manage', 'permissions.manage', 'menus.manage']);

    $this->actingAs($admin)
        ->get(route('applications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.fields.7.updateOnly', true)
            ->where('resource.fields.7.default', 'active'));

    $application = Application::factory()->make(['tenant_id' => $tenant->id]);
    $this->actingAs($admin)
        ->post(route('applications.store'), [
            'code' => $application->code,
            'name' => $application->name,
            'client_id' => $application->client_id,
            'client_secret' => 'secret123',
            'base_url' => $application->base_url,
            'redirect_uri' => $application->redirect_uri,
            'required_permissions' => [],
        ])
        ->assertRedirect();

    expect(Application::query()->where('client_id', $application->client_id)->value('status'))->toBe('active');
});

test('role management list includes company name', function () {
    $tenant = Tenant::factory()->create(['name' => '创量科技']);
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['roles.manage']);

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.columns.0', 'company_name')
            ->where('items.data.0.company_name', '创量科技'));
});

test('system management resource uses system wording', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $this->actingAs($admin)
        ->get(route('applications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.label', '系统管理')
            ->where('resource.createLabel', '新增系统'));
});

test('menu management list includes system name', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['menus.manage']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => '投放系统',
    ]);
    Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'application_id' => $application->id,
        'code' => 'campaigns',
        'title' => '计划管理',
    ]);

    $this->actingAs($admin)
        ->get(route('menus.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.fields.5.label', '所属系统')
            ->where('resource.columns.2', 'system_name')
            ->where('items.data.0.system_name', '投放系统'));
});

test('default menus use system and operation log titles', function () {
    $this->seed();

    expect(Menu::query()->where('code', 'applications')->value('title'))->toBe('系统管理');
    expect(Menu::query()->where('code', 'audit_logs')->value('title'))->toBe('操作日志');
});

test('application secret can be rotated and audited', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'client_secret' => 'old-secret',
    ]);

    $this->actingAs($admin)
        ->post(route('applications.rotate-secret', $application))
        ->assertRedirect()
        ->assertSessionHas('secret');

    expect(Hash::check('old-secret', $application->refresh()->client_secret))->toBeFalse();
    expect(AuditLog::query()->where('action', 'application.secret_rotated')->exists())->toBeTrue();
});

test('application changes bump permission version and hide client secret from admin props', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => 'billing-client',
        'client_secret' => 'visible-once',
        'required_permissions' => [],
    ]);
    $before = TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version');

    $this->actingAs($admin)
        ->put(route('applications.update', $application), [
            'code' => $application->code,
            'name' => $application->name,
            'client_id' => $application->client_id,
            'base_url' => $application->base_url,
            'redirect_uri' => $application->redirect_uri,
            'status' => 'active',
            'required_permissions' => ['applications.manage'],
        ])
        ->assertRedirect();

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $admin->id)
        ->value('permission_version'))->toBeGreaterThan($before);

    $this->get(route('applications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.client_id', 'billing-client')
            ->missing('items.data.0.client_secret'));
});

test('application detail groups sso config permissions menus authorization and checks', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage', 'orders.view']);

    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'orders',
        'name' => '订单系统',
        'client_secret' => 'visible-once',
        'required_permissions' => ['orders.view'],
    ]);

    Permission::query()
        ->where('code', 'orders.view')
        ->update([
            'application_id' => $application->id,
            'name' => '查看订单',
        ]);

    Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'application_id' => $application->id,
        'code' => 'orders',
        'title' => '订单管理',
        'required_permissions' => ['orders.view'],
    ]);

    $this->actingAs($admin)
        ->get(route('applications.show', $application))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ApplicationShow')
            ->where('application.code', 'orders')
            ->where('application.secret_configured', true)
            ->where('permissions.0.code', 'orders.view')
            ->where('menus.0.title', '订单管理')
            ->where('authorization.required_permissions.0', 'orders.view')
            ->where('checks.0.passed', true)
            ->missing('application.client_secret'));
});

test('permission snapshot exposes versioned local authorization data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    aucGrant($user, $tenant, ['dashboard.view']);

    Menu::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Dashboard',
        'required_permissions' => ['dashboard.view'],
    ]);
    Application::factory()->create([
        'tenant_id' => $tenant->id,
        'required_permissions' => ['dashboard.view'],
    ]);

    $this->actingAs($user)
        ->getJson(route('api.permissions.snapshot'))
        ->assertOk()
        ->assertJsonPath('permissions.0', 'dashboard.view')
        ->assertJsonCount(1, 'menus')
        ->assertJsonCount(1, 'applications');

    $this->getJson(route('api.permissions.version'))
        ->assertOk()
        ->assertJsonPath('permission_version', 1);
});

test('tenant admin cannot manage records in another tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['applications.manage']);

    $application = Application::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($admin)
        ->put(route('applications.update', $application), [
            'code' => 'blocked',
            'name' => 'Blocked',
            'client_id' => $application->client_id,
            'base_url' => 'https://blocked.example.test',
            'redirect_uri' => 'https://blocked.example.test/callback',
            'status' => 'active',
            'required_permissions' => [],
        ])
        ->assertForbidden();

    $this->get(route('applications.show', $application))->assertForbidden();
});

test('operation logs show enriched fields and can be filtered by action', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['audit_logs.view']);
    $application = Application::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => '订单系统',
    ]);

    AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'sso.code_issued',
        'subject_type' => Application::class,
        'subject_id' => $application->id,
        'ip_address' => '127.0.0.1',
        'metadata' => [
            'request' => [
                'client_id' => 'orders',
            ],
        ],
    ]);
    AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'role.updated',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($admin)
        ->get(route('audit-logs.index', ['search' => 'sso']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.label', '操作日志')
            ->where('resource.columns', ['operator_name', 'operated_at', 'company_name', 'system_name', 'operation_action', 'operation_object', 'request_params', 'ip_address'])
            ->where('items.data.0.operator_name', $admin->name)
            ->where('items.data.0.company_name', $tenant->name)
            ->where('items.data.0.system_name', '订单系统')
            ->where('items.data.0.operation_action', '签发授权码')
            ->where('items.data.0.operation_object', 'Application#'.$application->id)
            ->where('items.data.0.request_params', '{"client_id":"orders"}')
            ->where('items.data.0.ip_address', '127.0.0.1')
            ->has('items.data', 1));
});

test('platform admin can see operation logs from all companies', function () {
    $tenant = Tenant::factory()->create(['name' => 'A 公司']);
    $otherTenant = Tenant::factory()->create(['name' => 'B 公司']);
    $admin = User::factory()->platformAdmin()->create();
    aucGrant($admin, $tenant, ['audit_logs.view']);

    AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'tenant.updated',
        'ip_address' => '127.0.0.1',
    ]);
    AuditLog::query()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $admin->id,
        'action' => 'tenant.disabled',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($admin)
        ->get(route('audit-logs.index', ['tenant_id' => $tenant->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 2)
            ->where('items.data.0.company_name', 'B 公司')
            ->where('items.data.1.company_name', 'A 公司'));
});

test('company admin can only see current company operation logs', function () {
    $tenant = Tenant::factory()->create(['name' => 'A 公司']);
    $otherTenant = Tenant::factory()->create(['name' => 'B 公司']);
    $admin = User::factory()->create();
    aucGrant($admin, $tenant, ['audit_logs.view']);

    AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'tenant.updated',
        'ip_address' => '127.0.0.1',
    ]);
    AuditLog::query()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $admin->id,
        'action' => 'tenant.disabled',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($admin)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.company_name', 'A 公司')
            ->where('items.data.0.operation_action', '更新公司'));

    $this->get(route('audit-logs.index', ['tenant_id' => $otherTenant->id]))
        ->assertForbidden();
});

test('audit logger stores sanitized request parameters', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $request = Request::create('/applications', 'POST', [
        'name' => '订单系统',
        'client_secret' => 'plain-secret',
        'nested' => [
            'token' => 'plain-token',
            'safe' => 'value',
        ],
    ]);
    $request->setUserResolver(fn () => $admin);

    app(AuditLogger::class)->log($request, 'application.created', tenant: $tenant, metadata: [
        'secret' => 'metadata-secret',
        'safe' => 'metadata-value',
    ]);

    $metadata = AuditLog::query()->firstOrFail()->metadata;

    expect($metadata['secret'])->toBe('[filtered]');
    expect($metadata['safe'])->toBe('metadata-value');
    expect($metadata['request']['name'])->toBe('订单系统');
    expect($metadata['request']['client_secret'])->toBe('[filtered]');
    expect($metadata['request']['nested']['token'])->toBe('[filtered]');
    expect($metadata['request']['nested']['safe'])->toBe('value');
});
