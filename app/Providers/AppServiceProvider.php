<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AucAuthorization;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isPlatformAdmin() ? true : null);

        foreach ($this->aucPermissions() as $permission) {
            Gate::define($permission, function (User $user) use ($permission): bool {
                app(TenantContext::class)->resolveForRequest(request());

                return app(AucAuthorization::class)->userCan($user, $permission);
            });
        }

        Gate::define('tenants.manage', fn (User $user): bool => $user->isPlatformAdmin());
        Gate::define('diagnostics.view', fn (User $user): bool => $user->isPlatformAdmin());
    }

    /**
     * @return list<string>
     */
    private function aucPermissions(): array
    {
        return [
            'dashboard.view',
            'tenants.manage',
            'users.manage',
            'roles.manage',
            'permissions.manage',
            'menus.manage',
            'applications.manage',
            'audit_logs.view',
            'diagnostics.view',
        ];
    }
}
