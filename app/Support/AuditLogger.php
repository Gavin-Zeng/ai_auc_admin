<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(Request $request, string $action, ?Model $subject = null, ?Tenant $tenant = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $tenant?->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'metadata' => $metadata,
        ]);
    }
}
