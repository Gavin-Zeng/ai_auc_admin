<?php

namespace App\Models;

use Database\Factories\TenantApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantApplication extends Model
{
    /** @use HasFactory<TenantApplicationFactory> */
    use HasFactory;

    protected $table = 'auc_tenant_applications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'application_id',
        'required_permissions',
        'status',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
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
