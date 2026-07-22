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

    protected $fillable = ['name', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'auc_tenant_applications')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status;
    }

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
