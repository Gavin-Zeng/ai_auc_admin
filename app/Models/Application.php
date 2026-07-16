<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $table = 'auc_applications';

    /**
     * @var list<string>
     */
    protected $hidden = [
        'client_secret',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'base_url',
        'redirect_uri',
        'icon',
        'status',
    ];

    /**
     * @return HasMany<TenantApplication, $this>
     */
    public function tenantApplications(): HasMany
    {
        return $this->hasMany(TenantApplication::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function setClientSecretAttribute(string $value): void
    {
        $this->attributes['client_secret'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
