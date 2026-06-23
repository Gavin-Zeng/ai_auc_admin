<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    public function refresh(Request $request, array $identity): void
    {
        $current = $this->identity($request) ?? [];

        $request->session()->put(self::SessionKey, [
            ...$current,
            ...$identity,
        ]);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SessionKey);
    }

    public function isExpired(Request $request): bool
    {
        $expiresAt = $this->identity($request)['session_expires_at'] ?? null;

        return is_string($expiresAt) && Carbon::parse($expiresAt)->isPast();
    }

    public function hasPermission(Request $request, string $permission): bool
    {
        $identity = $this->identity($request);
        $permissions = $identity['permissions'] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
