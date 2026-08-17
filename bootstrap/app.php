<?php

use App\Http\Middleware\EnsureDemoSubsystemPermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'demo.permission' => EnsureDemoSubsystemPermission::class,
        ]);

        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state',
            'theme_brand',
            'theme_density',
            'theme_neutral',
            'theme_radius',
            'ui_theme',
        ]);
        $middleware->validateCsrfTokens(except: [
            'sso/token',
            'sso/logout',
        ]);

        $middleware->web(append: [
            IdentifyTenant::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
