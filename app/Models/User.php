<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'auc_users';

    protected $fillable = [
        'tenant_id', 'role_id', 'name', 'account', 'password',
        'is_company_admin', 'is_platform_admin', 'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function gamePermissions(): HasMany
    {
        return $this->hasMany(UserGamePermission::class);
    }

    public function isActive(): bool
    {
        return $this->status;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin && $this->isActive();
    }

    public function hasPlatformAccess(): bool
    {
        return $this->isPlatformAdmin();
    }

    public function isCompanyAdmin(?Tenant $tenant = null): bool
    {
        return $this->is_company_admin
            && $this->isActive()
            && ($tenant === null || $this->tenant_id === $tenant->id);
    }

    public function isCompanyOwner(Tenant $tenant): bool
    {
        return $this->isCompanyAdmin($tenant);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_company_admin' => 'boolean',
            'is_platform_admin' => 'boolean',
            'status' => 'boolean',
        ];
    }
}
