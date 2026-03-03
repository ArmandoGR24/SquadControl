<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role;

        if (! $userRole || ! in_array($userRole, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('tareas.mis');
        }

        return $next($request);
    }
}
