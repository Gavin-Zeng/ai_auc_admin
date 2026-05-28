<?php

namespace App\Support;

use Illuminate\Http\Request;

class DemoSubsystemSession
{
    public const SessionKey = 'demo_subsystem.identity';

    /**
     * @return array<string, mixed>|null
     */
    public function identity(Request $request): ?array
    {
        $identity = $request->session()->get(self::SessionKey);

        return is_array($identity) ? $identity : null;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    public function put(Request $request, array $identity): void
    {
        $request->session()->put(self::SessionKey, $identity);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SessionKey);
    }

    public function hasPermission(Request $request, string $permission): bool
    {
        $identity = $this->identity($request);
        $permissions = $identity['permissions'] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
