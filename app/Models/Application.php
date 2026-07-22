<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $table = 'auc_applications';

    protected $fillable = ['name', 'client_id', 'client_secret', 'status'];

    protected $hidden = ['client_secret'];

    public function urls(): HasMany
    {
        return $this->hasMany(ApplicationUrl::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'auc_tenant_applications')->withTimestamps();
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
