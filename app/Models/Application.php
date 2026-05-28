<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $table = 'auc_applications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'client_id',
        'client_secret',
        'base_url',
        'redirect_uri',
        'icon',
        'required_permissions',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
        return [
            'required_permissions' => 'array',
        ];
    }
}
