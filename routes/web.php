<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DiagnosticsController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\PermissionSnapshotController;
use App\Http\Controllers\Api\PermissionVersionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoSubsystemController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\TenantSwitchController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('tenant/switch', TenantSwitchController::class)->name('tenant.switch');

    Route::get('sso/authorize', [SsoController::class, 'authorize'])->middleware('throttle:60,1')->name('sso.authorize');
    Route::post('sso/token', [SsoController::class, 'token'])->withoutMiddleware(['auth', 'verified'])->middleware('throttle:120,1')->name('sso.token');
    Route::post('sso/logout', [SsoController::class, 'logout'])->withoutMiddleware(['auth', 'verified'])->middleware('throttle:60,1')->name('sso.logout');

    Route::get('api/me', MeController::class)->name('api.me');
    Route::get('api/navigation', NavigationController::class)->name('api.navigation');
    Route::get('api/permissions/version', PermissionVersionController::class)->name('api.permissions.version');
    Route::get('api/permissions/snapshot', PermissionSnapshotController::class)->name('api.permissions.snapshot');

    Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:tenants.manage');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:users.manage');
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:roles.manage');
    Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:permissions.manage');
    Route::resource('menus', MenuController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:menus.manage');
    Route::resource('applications', ApplicationController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->middleware('can:applications.manage');
    Route::post('applications/{application}/rotate-secret', [ApplicationController::class, 'rotateSecret'])->middleware('can:applications.manage')->name('applications.rotate-secret');
    Route::post('applications/{application}/tenant-applications', [ApplicationController::class, 'openForTenant'])->middleware('can:applications.manage')->name('applications.tenant-applications.store');
    Route::resource('audit-logs', AuditLogController::class)->only(['index'])->middleware('can:audit_logs.view');
    Route::get('diagnostics', DiagnosticsController::class)->middleware('can:diagnostics.view')->name('diagnostics.index');
});

Route::prefix('demo-subsystem')->name('demo-subsystem.')->group(function () {
    Route::get('sso/callback', [DemoSubsystemController::class, 'callback'])->name('callback');
    Route::get('login-required', [DemoSubsystemController::class, 'loginRequired'])->name('login-required');
    Route::get('dashboard', [DemoSubsystemController::class, 'dashboard'])->name('dashboard');
    Route::get('reports', [DemoSubsystemController::class, 'reports'])->middleware('demo.permission:dashboard.view')->name('reports');
    Route::post('permissions/refresh', [DemoSubsystemController::class, 'refreshPermissions'])->name('permissions.refresh');
    Route::post('auth/auc/logout', [DemoSubsystemController::class, 'logout'])->name('logout');
});

require __DIR__.'/settings.php';
