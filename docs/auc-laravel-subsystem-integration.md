# AUC Laravel 子系统接入指南

本文面向需要接入 AUC 后台的 Laravel 业务后台。接入目标是：用户先登录 AUC，在 AUC 工作台点击业务系统图标后，浏览器跳转到业务后台并完成免登录。

AUC 一期到四期采用内部 auth-code / login-ticket 协议，不是完整 OIDC Provider。子系统不共享 AUC session，不每次请求回源 AUC 鉴权，只在本地保存 session 和权限快照。

## 接入目标与前提

接入后应满足：

- 用户在 AUC 已登录时，点击应用图标进入子系统无需再次登录。
- 子系统通过一次性 `code` 向 AUC 换取身份和权限快照。
- 子系统建立自己的本地 session。
- 子系统本地保存或缓存 `auc_user_id`、tenant、roles、permissions、permission_version。
- 子系统接口鉴权使用本地权限快照。
- AUC 权限变更后，子系统通过 `permission_version` 感知并刷新权限快照或要求重新登录。

前提：

- AUC 已创建 tenant、用户、角色、权限和应用。
- 当前用户拥有访问该应用所需权限。
- 子系统拥有 AUC 应用配置中的 `client_id` 和 secret。
- 子系统回调地址必须与 AUC 应用配置的 `redirect_uri` 完全一致。

## AUC 后台应用配置项

在 AUC 后台“应用管理”中创建业务系统应用：

| 字段 | 说明 |
| --- | --- |
| 编码 | 应用唯一业务编码，例如 `crm-admin` |
| 名称 | 工作台展示名称 |
| 客户端 ID | 子系统换票使用的 `client_id` |
| 客户端密钥 | 子系统换票使用的 secret |
| 基础地址 | 子系统首页，例如 `https://crm.example.com` |
| 回调地址 | 子系统 SSO callback，例如 `https://crm.example.com/sso/callback` |
| 图标 | 工作台图标标识或 URL |
| 状态 | `active` 才允许 SSO |
| 所需权限 | 用户访问该应用必须拥有的 AUC 权限 |

secret 只在创建或轮换时展示一次。后续不能查看明文，只能轮换。子系统必须把 secret 放到 `.env`，不要写死在代码里。

## 子系统 `.env` 配置

业务后台增加以下配置：

```dotenv
AUC_BASE_URL=https://auc.example.com
AUC_CLIENT_ID=crm-admin-client
AUC_CLIENT_SECRET=replace-with-secret-shown-once
AUC_REDIRECT_URI=https://crm.example.com/sso/callback
AUC_SESSION_KEY=auc_identity
```

建议在 `config/services.php` 中集中读取：

```php
'auc' => [
    'base_url' => env('AUC_BASE_URL'),
    'client_id' => env('AUC_CLIENT_ID'),
    'client_secret' => env('AUC_CLIENT_SECRET'),
    'redirect_uri' => env('AUC_REDIRECT_URI'),
    'session_key' => env('AUC_SESSION_KEY', 'auc_identity'),
],
```

## 子系统数据表建议

最小接入可以只使用 session。生产环境建议增加本地用户映射和权限快照表，便于审计、权限刷新和排查。

示例字段：

```php
Schema::create('auc_user_mappings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->unsignedBigInteger('auc_user_id')->unique();
    $table->string('auc_email')->nullable();
    $table->string('auc_name')->nullable();
    $table->timestamps();
});

Schema::create('auc_permission_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->unsignedBigInteger('auc_tenant_id');
    $table->string('auc_tenant_code');
    $table->json('roles');
    $table->json('permissions');
    $table->unsignedInteger('permission_version')->default(1);
    $table->timestamp('session_expires_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'auc_tenant_id']);
});
```

如果子系统没有自己的用户体系，也可以把 `auc_user_id` 作为本地用户主键映射来源，但仍建议保留本地用户表，避免后续业务数据直接依赖外部用户表。

## 路由设计

子系统至少实现三个入口：

```php
use App\Http\Controllers\AucSsoController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/callback', [AucSsoController::class, 'callback'])->name('auc.callback');
Route::post('/auth/auc/logout', [AucSsoController::class, 'logout'])->name('auc.logout');
Route::post('/auth/auc/permissions/refresh', [AucSsoController::class, 'refreshPermissions'])
    ->middleware('auth')
    ->name('auc.permissions.refresh');
```

受保护业务路由使用本地 session 或本地登录态：

```php
Route::middleware(['auth', 'auc.permission:orders.view'])->group(function () {
    Route::get('/orders', OrdersController::class)->name('orders.index');
});
```

## Token exchange 客户端

子系统 callback 收到 AUC 带回的 `code` 后，服务端调用 AUC `/sso/token` 换票。

```php
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AucSsoClient
{
    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asJson()
            ->timeout(5)
            ->connectTimeout(3)
            ->post(config('services.auc.base_url').'/sso/token', [
                'client_id' => config('services.auc.client_id'),
                'client_secret' => config('services.auc.client_secret'),
                'code' => $code,
                'redirect_uri' => config('services.auc.redirect_uri'),
            ]);

        if ($response->failed()) {
            abort($response->status(), $response->json('message', 'AUC 换票失败。'));
        }

        return $response->json();
    }
}
```

`/sso/token` 成功返回：

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com"
  },
  "tenant": {
    "id": 1,
    "code": "default",
    "name": "默认租户",
    "status": "active"
  },
  "roles": ["operator"],
  "permissions": ["dashboard.view", "orders.view"],
  "permission_version": 1,
  "session_expires_at": "2026-05-28T10:00:00.000000Z"
}
```

## 本地用户映射与 session 建立

callback 控制器负责验证 `code`、换票、映射用户、写入权限快照、建立本地 session。

```php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AucSsoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AucSsoController
{
    public function callback(Request $request, AucSsoClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $payload = $client->exchangeCode($validated['code']);

        $user = User::query()->updateOrCreate([
            'auc_user_id' => $payload['user']['id'],
        ], [
            'name' => $payload['user']['name'],
            'email' => $payload['user']['email'],
        ]);

        Auth::login($user);

        $request->session()->put(config('services.auc.session_key'), [
            'auc_user_id' => $payload['user']['id'],
            'user' => $payload['user'],
            'tenant' => $payload['tenant'],
            'roles' => $payload['roles'],
            'permissions' => $payload['permissions'],
            'permission_version' => $payload['permission_version'],
            'session_expires_at' => $payload['session_expires_at'],
        ]);

        return redirect()->intended('/dashboard');
    }
}
```

如果子系统已有自己的用户表，建议新增 `auc_user_id` 字段并建立唯一索引。用户映射以 `auc_user_id` 为准，邮箱只作为展示或辅助匹配字段。

## 本地权限中间件示例

子系统接口鉴权必须在服务端执行。菜单隐藏只影响入口可见性，不能替代接口权限校验。

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAucPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $identity = $request->session()->get(config('services.auc.session_key'), []);
        $permissions = $identity['permissions'] ?? [];

        abort_unless(
            in_array('*', $permissions, true) || in_array($permission, $permissions, true),
            403
        );

        return $next($request);
    }
}
```

在 `bootstrap/app.php` 注册 middleware alias：

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'auc.permission' => \App\Http\Middleware\EnsureAucPermission::class,
    ]);
})
```

## `permission_version` 刷新策略

AUC 修改用户角色、权限、菜单或应用授权后，会提升当前 tenant 下用户的 `permission_version`。子系统可以用两种方式处理：

1. 进入关键页面前检查版本。
2. 用户点击“刷新权限”或后台定时轮询版本。

推荐子系统提供一个刷新入口：

```php
public function refreshPermissions(Request $request): RedirectResponse
{
    $identity = $request->session()->get(config('services.auc.session_key'));

    if (! is_array($identity)) {
        return redirect('/login');
    }

    $response = Http::withHeaders([
        'Accept' => 'application/json',
    ])->get(config('services.auc.base_url').'/api/permissions/snapshot');

    if ($response->failed()) {
        Auth::logout();
        $request->session()->forget(config('services.auc.session_key'));

        return redirect('/login')->withErrors([
            'auc' => '权限快照已失效，请从 AUC 工作台重新进入。',
        ]);
    }

    $snapshot = $response->json();

    $request->session()->put(config('services.auc.session_key'), [
        ...$identity,
        'tenant' => $snapshot['tenant'],
        'roles' => $snapshot['roles'],
        'permissions' => $snapshot['permissions'],
        'permission_version' => $snapshot['permission_version'],
    ]);

    return back()->with('status', '权限快照已刷新。');
}
```

注意：真实独立子系统通常拿不到 AUC 浏览器 session，因此直接请求 `/api/permissions/snapshot` 需要额外认证上下文。生产建议使用“重新从 AUC 工作台进入”作为兜底刷新方式；如果要做无感刷新，需要后续在 AUC 增加面向子系统的服务端刷新接口。

## 退出流程

一期到四期不做完整单点登出。子系统退出时先清理本地 session：

```php
public function logout(Request $request): RedirectResponse
{
    Auth::logout();

    $request->session()->forget(config('services.auc.session_key'));
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    Http::asJson()
        ->timeout(3)
        ->connectTimeout(2)
        ->post(config('services.auc.base_url').'/sso/logout', [
            'client_id' => config('services.auc.client_id'),
        ]);

    return redirect('/login');
}
```

AUC `/sso/logout` 当前返回 `202`，语义是“已接收退出请求，子系统应清理本地 session”。完整单点登出可在后续版本实现。

## AUC 接口说明

### `GET /sso/authorize`

浏览器跳转入口，由 AUC 工作台应用图标生成。子系统通常不需要手写这个地址。

参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `client_id` | 是 | AUC 应用客户端 ID |
| `redirect_uri` | 是 | 必须与 AUC 应用配置完全一致 |
| `tenant_id` | 否 | 指定租户 |
| `tenant_code` | 否 | 指定租户编码 |
| `state` | 否 | 子系统自定义状态，AUC 会原样带回 |

成功后跳转到：

```text
{redirect_uri}?code=xxx&state=yyy
```

### `POST /sso/token`

服务端换票接口。

请求：

```json
{
  "client_id": "crm-admin-client",
  "client_secret": "secret",
  "code": "one-time-code",
  "redirect_uri": "https://crm.example.com/sso/callback"
}
```

失败错误码：

| error | HTTP | 说明 |
| --- | --- | --- |
| `invalid_client` | 401 | `client_id` 不存在或 secret 错误 |
| `redirect_uri_mismatch` | 422 | 回调地址不匹配 |
| `invalid_code` | 422 | code 不存在或不属于当前应用 |
| `code_already_used` | 422 | code 已兑换，疑似重放 |
| `code_expired` | 422 | code 超过有效期 |
| `tenant_disabled` | 403 | 租户禁用 |
| `application_disabled` | 403 | 应用禁用 |

### `POST /sso/logout`

当前为退出占位接口。子系统应自行清理本地 session。

### `GET /api/permissions/version`

返回当前 AUC session 下用户的权限版本。

```json
{
  "tenant_id": 1,
  "permission_version": 2
}
```

### `GET /api/permissions/snapshot`

返回当前 AUC session 下用户的权限、菜单和应用快照。独立子系统默认不要依赖它做每请求鉴权。

## 接入验收清单

上线前逐项确认：

- AUC 应用为 `active`。
- AUC 应用 `redirect_uri` 与子系统 `.env` 完全一致。
- 子系统 `.env` 已配置 `AUC_BASE_URL`、`AUC_CLIENT_ID`、`AUC_CLIENT_SECRET`、`AUC_REDIRECT_URI`。
- secret 没有写入代码仓库。
- AUC 登录后点击应用，子系统 callback 成功换票。
- 子系统本地 session 写入 `auc_user_id`、tenant、roles、permissions、permission_version。
- 无权限用户无法从 AUC 工作台进入该应用。
- 无权限用户直接访问子系统受保护接口返回 403。
- code 重复兑换失败。
- code 过期兑换失败。
- 应用禁用或租户禁用后无法换票。
- AUC 修改角色权限后，子系统能感知 `permission_version` 变化，刷新权限快照或要求重新登录。

## 常见失败原因排查

### `invalid_client`

可能原因：

- `client_id` 填错。
- secret 填错。
- AUC 应用 secret 已轮换，但子系统 `.env` 未更新。

处理：

- 在 AUC 后台重新轮换 secret。
- 更新子系统 `.env`。
- 清理配置缓存：`php artisan config:clear`。

### `redirect_uri_mismatch`

可能原因：

- AUC 后台配置的是 `https://crm.example.com/sso/callback`，子系统请求传的是 `http://crm.example.com/sso/callback`。
- 末尾斜杠、端口、路径、域名不完全一致。

处理：

- 以 AUC 后台应用配置为准，让 `.env` 中 `AUC_REDIRECT_URI` 完全一致。

### `invalid_code`

可能原因：

- code 被截断或传错。
- code 属于另一个应用。
- 子系统使用了错误的 `client_id`。

### `code_already_used`

可能原因：

- 浏览器刷新 callback 页面。
- 子系统重试了同一个 code。
- code 泄漏后被重复兑换。

处理：

- callback 换票成功后立即跳转到业务页。
- 不要在日志中记录完整 code。

### `code_expired`

AUC code 当前短期有效。用户停留太久或网络异常时可能过期。

处理：

- 引导用户回到 AUC 工作台重新点击应用。

### `tenant_disabled`

当前租户已禁用或过期。

处理：

- 在 AUC 租户管理中恢复租户状态。

### `application_disabled`

应用已停用。

处理：

- 在 AUC 应用管理中启用应用。

### 子系统受保护接口没有拦住

可能原因：

- 路由没有加 `auc.permission:*` middleware。
- middleware 读取的 session key 与写入时不一致。
- 子系统只隐藏了菜单，没有做服务端鉴权。

处理：

- 所有关键写操作和敏感页面都必须加服务端权限中间件或 Policy。
