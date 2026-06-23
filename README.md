# AUC Admin

AUC Admin 是一个基于 Laravel、Inertia 和 Vue 的后台管理项目，包含租户、用户、角色、权限、菜单、应用、审计日志、运维诊断以及 AUC SSO 演示子系统相关功能。

## 环境依赖

- PHP 8.4，项目约束为 `^8.3`
- Composer
- Node.js 与 npm
- SQLite 或 MySQL

当前项目主要框架与工具：

- Laravel 13
- Inertia Laravel 3
- Vue 3
- Tailwind CSS 4
- Laravel Fortify
- Laravel Wayfinder
- Pest 4

## 初始化项目

首次拉取代码后，在项目根目录执行：

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

默认 `.env.example` 使用 SQLite：

```dotenv
DB_CONNECTION=sqlite
```

如果本地没有数据库文件，先创建：

```bash
touch database/database.sqlite
```

然后执行迁移和初始化数据：

```bash
php artisan migrate --seed
```

也可以使用项目内置脚本完成依赖安装、环境文件生成、密钥生成、迁移和前端构建：

```bash
composer run setup
```

注意：`composer run setup` 会执行 `php artisan migrate --force` 和 `npm run build`，更适合全新环境初始化。

## 启动开发环境

推荐使用 Composer 脚本同时启动 Laravel 服务、队列监听、日志输出和 Vite：

```bash
composer run dev
```

该命令会同时运行：

- `php artisan serve`
- `php artisan queue:listen --tries=1 --timeout=0`
- `php artisan pail --timeout=0`
- `npm run dev`

默认访问地址通常是：

```text
http://127.0.0.1:8000
```

如果只想分别启动后端和前端，可以开两个终端：

```bash
php artisan serve
npm run dev
```

## 默认测试数据

执行 `php artisan migrate --seed` 后会创建默认租户、权限、管理员角色、菜单和一个测试用户：

```text
邮箱：test@example.com
```

测试用户密码由 `UserFactory` 决定。Laravel 默认工厂通常使用 `password`，如无法登录请查看 `database/factories/UserFactory.php`。

Seeder 还会创建一个演示应用：

```text
应用编码：auc-admin
客户端 ID：auc-admin
客户端密钥：secret
回调地址：{APP_URL}/demo-subsystem/sso/callback
```

## 常用命令

```bash
# 运行测试
php artisan test --compact

# PHP 代码格式化
vendor/bin/pint --format agent

# 前端格式化
npm run format

# 前端格式化检查
npm run format:check

# 前端 lint
npm run lint

# 前端 lint 检查
npm run lint:check

# TypeScript 类型检查
npm run types:check

# 前端生产构建
npm run build

# 查看路由
php artisan route:list
```

Composer 也提供了整体验证脚本：

```bash
composer run ci:check
```

## 目录说明

- `app/`：Laravel 后端代码
- `routes/`：Web、设置页和控制台路由
- `resources/js/pages/`：Inertia Vue 页面
- `resources/js/components/`：前端组件
- `database/migrations/`：数据库迁移
- `database/seeders/`：初始化数据
- `tests/Feature/`：功能测试
- `docs/`：项目补充文档

## AUC 子系统接入

子系统接入说明见：

```text
docs/auc-laravel-subsystem-integration.md
```

该文档说明了 AUC 应用配置、SSO callback、token exchange、本地 session、权限快照和子系统鉴权建议。

## 常见问题

### 页面样式或前端修改没有生效

确认 Vite 正在运行：

```bash
npm run dev
```

或直接使用：

```bash
composer run dev
```

### 出现 Vite manifest 找不到文件

开发环境启动 Vite：

```bash
npm run dev
```

生产或不运行 Vite 时先构建前端资源：

```bash
npm run build
```

### 修改 `.env` 后配置没有生效

清理配置缓存：

```bash
php artisan config:clear
```

### 数据库表不存在

确认已经执行迁移：

```bash
php artisan migrate
```

如果需要初始化演示数据：

```bash
php artisan db:seed
```
