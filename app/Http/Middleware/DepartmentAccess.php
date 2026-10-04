<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DepartmentAccess
{
    public function handle(Request $request, Closure $next, string ...$departments): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Authentication required.');
        }

        if ($user->hasRole('super-admin') || $user->hasRole('hospital-manager')) {
            return $next($request);
        }

        $allowedDepartments = array_values(array_filter($departments, static fn (string $department): bool => $department !== ''));

        if ($allowedDepartments === []) {
            return $next($request);
        }

        abort_unless($user->canAccessDepartments($allowedDepartments), 403, 'You do not have access to this department.');

        return $next($request);
    }
}
