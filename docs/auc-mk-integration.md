# 国内联运发行平台 AUC 接入指南

本文面向后续要用 Codex AI 新建“国内联运发行平台”后台的工程师。目标是：新后台基于 Laravel 13 + Inertia Vue 开发，通过现有 AUC 完成统一登录、统一入口、统一权限快照同步。

这份文档是专项接入文档。通用 Laravel 子系统接入说明仍保留在 `docs/auc-laravel-subsystem-integration.md`，本文在它的基础上，改成更贴近“国内联运发行平台”场景的落地版本。

## 1. 接入目标

接入完成后，应满足以下效果：

- 用户先登录 AUC。
- 用户在 AUC 工作台点击“国内联运发行平台”系统卡片。
- 浏览器跳转到 `mk-admin.bingniao.ai`。
- 子系统通过一次性 `code` 向 AUC 换票。
- 子系统创建自己的本地 session，不共享 AUC session。
- 子系统本地保存 `auc_user_id`、tenant、roles、permissions、permission_version。
- 子系统接口鉴权依赖本地权限快照，不每次请求回源 AUC。

当前协议仍是内部 auth-code / login-ticket 协议，不是完整 OIDC Provider。

## 2. 本地联调域名

本地联调要求同时配置 AUC 域名和子系统域名，不建议继续混用 `localhost` 作为主示例。

建议在本机 `hosts` 中加入：

```text
127.0.0.1 localauc.bingniao.ai
127.0.0.1 mk-admin.bingniao.ai
```

说明：

- `localauc.bingniao.ai`：本地 AUC 域名。
- `mk-admin.bingniao.ai`：国内联运发行平台后台本地域名。

如果 AUC 和子系统都跑在本机，Laravel 启动后应保证这两个域名都能访问到对应服务。

## 3. AUC 端系统配置

在 AUC 后台“系统管理”中创建“国内联运发行平台”系统，建议按下面配置：

| 字段 | 建议值 | 说明 |
| --- | --- | --- |
| 名称 | 国内联运发行平台 | AUC 工作台展示名 |
| 客户端 ID | `mk-admin` | 子系统唯一标识，也是 SSO `client_id` |
| 客户端密钥 | 创建时生成 | 只展示一次，后续只能轮换 |
| 基础地址 | `http://mk-admin.bingniao.ai` | 子系统首页地址 |
| 回调地址 | `http://mk-admin.bingniao.ai/sso/callback` | 必须与子系统完全一致 |
| 入口权限 | 例如 `applications.manage（管理系统）` 或其他业务入口权限 | 控制谁能在 AUC 工作台看到并进入该系统 |
| 状态 | `active` | 停用后 AUC 不再允许发起 SSO |

关键约束：

- `redirect_uri` 必须与子系统 `.env` 中的 `AUC_REDIRECT_URI` 完全一致。
- 客户端密钥必须写入子系统 `.env`，不要硬编码到代码里。
- 入口权限只控制系统入口可见性和 AUC authorize 准入，不替代子系统内部接口鉴权。

## 4. AUC 本地环境建议

AUC 本地 `.env` 建议使用：

```dotenv
APP_URL=http://localauc.bingniao.ai
SESSION_DOMAIN=.bingniao.ai
```

说明：

- `APP_URL` 统一为本地域名，便于 AUC 生成系统跳转地址和回调说明。
- `SESSION_DOMAIN=.bingniao.ai` 不是共享登录态的前提，但对于本地域名体系下的联调更一致，便于排查 Cookie 和跳转问题。

如果本地仍使用 `php artisan serve`，要确认浏览器访问地址与文档中的域名一致，而不是继续打开 `127.0.0.1:8000`。

## 5. 子系统 `.env` 建议

国内联运发行平台后台建议增加以下环境变量：

```dotenv
APP_URL=http://mk-admin.bingniao.ai
SESSION_DOMAIN=.bingniao.ai

AUC_BASE_URL=http://localauc.bingniao.ai
AUC_CLIENT_ID=mk-admin
AUC_CLIENT_SECRET=replace-with-secret-shown-once
AUC_REDIRECT_URI=http://mk-admin.bingniao.ai/sso/callback
AUC_SESSION_KEY=auc_identity
```

再在 `config/services.php` 中集中读取：

```php
'auc' => [
    'base_url' => env('AUC_BASE_URL'),
    'client_id' => env('AUC_CLIENT_ID'),
    'client_secret' => env('AUC_CLIENT_SECRET'),
    'redirect_uri' => env('AUC_REDIRECT_URI'),
    'session_key' => env('AUC_SESSION_KEY', 'auc_identity'),
],
```

## 6. 子系统路由设计

国内联运发行平台至少实现以下三个入口：

```php
use App\Http\Controllers\AucSsoController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/callback', [AucSsoController::class, 'callback'])->name('auc.callback');
Route::post('/auth/auc/logout', [AucSsoController::class, 'logout'])->name('auc.logout');
Route::post('/auth/auc/permissions/refresh', [AucSsoController::class, 'refreshPermissions'])
    ->middleware('auth')
    ->name('auc.permissions.refresh');
```

受保护业务路由继续使用本地 session 和本地权限中间件：

```php
Route::middleware(['auth', 'auc.permission:dashboard.view'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
```

## 7. AUC 对外接口

接入文档中应以当前 AUC 的真实接口为准：

### 浏览器跳转入口

- `GET /sso/authorize`

请求参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `client_id` | 是 | AUC 中配置的客户端 ID |
| `redirect_uri` | 是 | 子系统回调地址，必须完全匹配 |
| `tenant_id` | 否 | 显式指定 tenant |
| `tenant_code` | 否 | 显式指定 tenant code |
| `state` | 否 | 子系统自定义透传字段 |

### 后端换票接口

- `POST /sso/token`

请求体：

```json
{
  "client_id": "mk-admin",
  "client_secret": "replace-with-secret",
  "code": "one-time-code",
  "redirect_uri": "http://mk-admin.bingniao.ai/sso/callback"
}
```

成功响应：

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
  "permissions": ["dashboard.view", "users.manage"],
  "permission_version": 1,
  "menus": [],
  "session_expires_at": "2026-06-25T12:00:00.000000Z"
}
```

### 其他辅助接口

- `POST /sso/logout`
- `GET /api/me`
- `GET /api/navigation`
- `GET /api/permissions/version`
- `GET /api/permissions/snapshot`

## 8. Laravel 子系统换票客户端

子系统 callback 收到 AUC 带回的 `code` 后，由服务端向 AUC `/sso/token` 换票：

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

## 9. 本地 session 建立

`GET /sso/callback` 的控制器职责：

1. 校验 `code`
2. 调用 AUC `/sso/token`
3. 映射本地用户
4. 写入权限快照
5. 建立本地 session
6. 跳到子系统首页

参考结构：

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

## 10. 本地权限中间件

国内联运发行平台的接口鉴权必须在服务端执行，不能依赖 AUC 菜单隐藏：

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

## 11. `permission_version` 刷新策略

AUC 修改当前公司下用户的角色、权限、菜单或系统入口权限后，会提升该用户的 `permission_version`。子系统推荐两种策略：

### 方案一：进入后台时比对版本

- 子系统在登录后保存 `permission_version`
- 每次进入后台首页，调用 `GET /api/permissions/version`
- 若版本变化，再调用 `GET /api/permissions/snapshot` 覆盖本地快照

### 方案二：关键页面手动刷新

- 在管理后台头部或安全设置页提供“刷新权限快照”按钮
- 按需调用 `POST /auth/auc/permissions/refresh`

本期推荐方案一，方案二作为管理员排查辅助。

## 12. 退出流程

建议退出顺序：

1. 子系统清理自己的本地 session
2. 可选调用 AUC `POST /sso/logout` 记录审计
3. 子系统跳回登录提示页或 AUC 工作台

注意：当前 AUC `POST /sso/logout` 只记录退出请求，不会主动清理其他系统 session。

## 13. 联调验收清单

联调时至少验证以下场景：

1. 用户已登录 AUC，点击“国内联运发行平台”后，跳到 `mk-admin.bingniao.ai`
2. 子系统 `callback` 能成功换票并建立本地 session
3. 本地 session 写入 `auc_user_id / tenant / roles / permissions / permission_version`
4. 子系统受保护页面可正常访问
5. 无入口权限的用户在 AUC 工作台看不到该系统，或点击后被拒绝
6. AUC 调整角色权限后，子系统能感知 `permission_version` 变化并刷新快照
7. 子系统退出后本地 session 被清理

## 14. 常见失败原因排查

### `invalid_client`

常见原因：

- `client_id` 填错
- `client_secret` 不是 AUC 创建或轮换时展示的明文
- 子系统 `.env` 未刷新

排查：

- 对照 AUC 系统管理中的 `client_id`
- 重新轮换密钥并更新 `.env`
- 执行 `php artisan config:clear`

### `redirect_uri_mismatch`

常见原因：

- 子系统 `.env` 中的 `AUC_REDIRECT_URI` 与 AUC 配置不一致
- 本地域名仍在用 `localhost`
- 回调路径大小写或端口不一致

排查：

- 以 AUC 系统管理中的 `redirect_uri` 为准
- 确认浏览器实际访问域名为 `mk-admin.bingniao.ai`
- 检查 `hosts` 是否生效

### `invalid_code`

常见原因：

- callback 带回的 `code` 已失效
- 子系统重复使用同一个 `code`
- 换票时 `redirect_uri` 不一致

排查：

- 重新从 AUC 工作台进入一次
- 确认 callback 只换票一次
- 检查 `redirect_uri`

### `code_already_used`

常见原因：

- callback 被重复刷新
- 前后端重复发起换票

排查：

- callback 成功后立即 302 到业务首页
- 不要让前端再次直接调用 `/sso/token`

### `code_expired`

常见原因：

- 用户停留在 callback 页面太久
- 本地调试中断过久后才继续换票

排查：

- 从 AUC 工作台重新点击进入

### `tenant_disabled`

常见原因：

- 当前公司被停用

排查：

- 在 AUC 公司管理中确认当前公司状态

### `application_disabled`

常见原因：

- “国内联运发行平台”在 AUC 中被停用

排查：

- 在 AUC 系统管理中检查该系统状态是否为 `active`

### 子系统提示“未建立本地 session”

常见原因：

- callback 换票失败
- 子系统 session 没写进去
- `SESSION_DOMAIN` 配置不一致

排查：

- 先看 callback 响应和后端日志
- 确认子系统 `.env` 中 `APP_URL`、`SESSION_DOMAIN` 已设置为 `mk-admin.bingniao.ai` 体系
- 确认浏览器 Cookie 已正确写入

### `hosts` 未生效

常见原因：

- 本机 `hosts` 未保存成功
- DNS 缓存未刷新
- 实际打开的还是旧域名或 IP

排查：

- 浏览器直接访问 `http://mk-admin.bingniao.ai`
- 浏览器直接访问 `http://localauc.bingniao.ai`
- 确认它们都能命中本机服务

## 15. 推荐执行顺序

建议按以下顺序落地：

1. 先在 AUC 系统管理创建“国内联运发行平台”
2. 配好本地 `hosts`
3. 初始化 Laravel 13 + Inertia Vue 子系统工程
4. 写 `.env` 和 `config/services.php`
5. 增加 callback / logout / permissions refresh 路由
6. 写换票客户端和 session 建立逻辑
7. 增加本地权限中间件
8. 从 AUC 工作台做首次联调
9. 验证权限版本刷新和异常场景

## 16. 相关资料

- 通用 Laravel 子系统接入文档：`docs/auc-laravel-subsystem-integration.md`
- 当前 AUC demo 子系统示例：
  - `app/Http/Controllers/DemoSubsystemController.php`
  - `resources/js/pages/demo-subsystem/Dashboard.vue`
  - `tests/Feature/DemoSubsystemSsoTest.php`
