<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantUser;

class PermissionVersion
{
    public function bump(Tenant $tenant): void
    {
        TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->increment('permission_version');
    }
}
