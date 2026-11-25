<?php

namespace Solt\Bonus\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ограничение по роли: ->middleware('admin.role:admin') или 'admin.role:admin,editor'.
 */
class AdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, $roles, true)) {
            abort(403, 'Недостаточно прав для этого действия');
        }
        return $next($request);
    }
}
