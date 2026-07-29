<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\GamePermissionController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoSubsystemController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome', [
    'canRegister' => false,
])->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('sso/authorize', [SsoController::class, 'authorize'])->middleware('throttle:60,1')->name('sso.authorize');
    Route::post('sso/token', [SsoController::class, 'token'])->withoutMiddleware(['auth', 'verified'])->middleware('throttle:120,1')->name('sso.token');
    Route::post('sso/logout', [SsoController::class, 'logout'])->withoutMiddleware(['auth', 'verified'])->middleware('throttle:60,1')->name('sso.logout');

    Route::middleware('can:tenants.manage')->group(function () {
        Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update', 'destroy']);
    });
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:users.manage');
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:roles.manage');
    Route::resource('menus', MenuController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:menus.manage');
    Route::resource('applications', ApplicationController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:applications.manage');
    Route::post('applications/{application}/rotate-secret', [ApplicationController::class, 'rotateSecret'])->middleware('can:applications.manage')->name('applications.rotate-secret');
    Route::get('games', [GameController::class, 'index'])->middleware('can:games.manage')->name('games.index');
    Route::get('game-permissions', [GamePermissionController::class, 'redirectToUsers'])->middleware('can:users.manage')->name('game-permissions.index');
    Route::get('users/{user}/game-permissions', [GamePermissionController::class, 'index'])->middleware('can:users.manage')->name('users.game-permissions.index');
    Route::put('users/{user}/game-permissions', [GamePermissionController::class, 'update'])->middleware('can:users.manage')->name('users.game-permissions.update');
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
