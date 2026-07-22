<?php

namespace App\Support;

use App\Models\Tenant;

class PermissionVersion
{
    public function bump(Tenant $tenant): void
    {
        $tenant->users()->touch();
    }
}
