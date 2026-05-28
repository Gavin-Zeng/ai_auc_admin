<?php

namespace App\Http\Middleware;

use App\Support\DemoSubsystemSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoSubsystemPermission
{
    public function __construct(private readonly DemoSubsystemSession $session) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($this->session->hasPermission($request, $permission), 403);

        return $next($request);
    }
}
