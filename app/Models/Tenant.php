<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $table = 'auc_tenants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'domain',
        'status',
        'expires_at',
        'settings',
    ];

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'auc_tenant_users')
            ->withPivot(['status', 'is_owner', 'permission_version'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TenantApplication, $this>
     */
    public function tenantApplications(): HasMany
    {
        return $this->hasMany(TenantApplication::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'settings' => 'array',
        ];
    }
}
