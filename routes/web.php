<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\DashboardController;
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

    Route::get('sso/authorize', [SsoController::class, 'authorize'])->name('sso.authorize');
    Route::post('sso/token', [SsoController::class, 'token'])->withoutMiddleware(['auth', 'verified'])->name('sso.token');
    Route::post('sso/logout', [SsoController::class, 'logout'])->withoutMiddleware(['auth', 'verified'])->name('sso.logout');

    Route::get('api/me', MeController::class)->name('api.me');
    Route::get('api/navigation', NavigationController::class)->name('api.navigation');

    Route::inertia('tenants', 'admin/Placeholder', ['title' => 'Tenant Management'])->middleware('can:tenants.manage')->name('tenants.index');
    Route::inertia('users', 'admin/Placeholder', ['title' => 'User Management'])->middleware('can:users.manage')->name('users.index');
    Route::inertia('roles', 'admin/Placeholder', ['title' => 'Role Management'])->middleware('can:roles.manage')->name('roles.index');
    Route::inertia('permissions', 'admin/Placeholder', ['title' => 'Permission Management'])->middleware('can:permissions.manage')->name('permissions.index');
    Route::inertia('menus', 'admin/Placeholder', ['title' => 'Menu Management'])->middleware('can:menus.manage')->name('menus.index');
    Route::inertia('applications', 'admin/Placeholder', ['title' => 'Application Management'])->middleware('can:applications.manage')->name('applications.index');
    Route::inertia('audit-logs', 'admin/Placeholder', ['title' => 'Audit Logs'])->middleware('can:audit_logs.view')->name('audit-logs.index');
});

require __DIR__.'/settings.php';
