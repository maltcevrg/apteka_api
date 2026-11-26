<?php

namespace Solt\Bonus\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ в админ-панель только после входа (логин + пароль, сессия).
 * Для ролей с правом изменения — автовыход после N минут бездействия
 * (config bonus.admin_idle_minutes, env BONUS_ADMIN_IDLE).
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('admin.login')->with('error', 'Войдите в систему');
        }

        $idle = (int) config('bonus.admin_idle_minutes', 30);
        if ($idle > 0 && $request->user()->canEdit()) {
            $last = $request->session()->get('admin_last_activity');
            if ($last && now()->timestamp - $last > $idle * 60) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('admin.login')->with('info', "Сеанс завершён после {$idle} минут бездействия — войдите снова");
            }
            $request->session()->put('admin_last_activity', now()->timestamp);
        }

        return $next($request);
    }
}
