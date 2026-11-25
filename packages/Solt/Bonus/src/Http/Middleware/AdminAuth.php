<?php

namespace Solt\Bonus\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ в админ-панель только после входа (логин + пароль, сессия).
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('admin.login')->with('error', 'Войдите в систему');
        }
        return $next($request);
    }
}
