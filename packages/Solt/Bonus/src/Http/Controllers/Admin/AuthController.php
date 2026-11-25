<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('bonus::admin.login');
    }

    /**
     * Шаг 1: логин. Если пароль ещё не задан — на страницу создания пароля.
     * Иначе — проверка логина и пароля.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string'],
        ]);

        $user = User::where('login', $data['login'])->first();

        if ($user && $user->needsPassword()) {
            $request->session()->put('setup_login', $user->login);
            return redirect()->route('admin.password.setup');
        }

        if (!$user || ($data['password'] ?? '') === ''
            || !Auth::attempt(['login' => $data['login'], 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput(['login' => $data['login']])->with('error', 'Неверный логин или пароль');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        return redirect()->intended(route('admin.dashboard'));
    }

    /** Первый вход: форма создания пароля. */
    public function showSetup(Request $request)
    {
        $login = $request->session()->get('setup_login');
        $user = $login ? User::where('login', $login)->first() : null;
        if (!$user || !$user->needsPassword()) {
            return redirect()->route('admin.login');
        }
        return view('bonus::admin.password_setup', ['user' => $user, 'rules' => User::passwordRulesMessage()]);
    }

    public function setup(Request $request)
    {
        $login = $request->session()->get('setup_login');
        $user = $login ? User::where('login', $login)->first() : null;
        if (!$user || !$user->needsPassword()) {
            return redirect()->route('admin.login')->with('error', 'Пароль уже задан — войдите по логину и паролю');
        }

        $data = $request->validate(['password' => User::passwordRules()], ['password.regex' => User::passwordRulesMessage()]);

        $user->password = $data['password'];
        $user->last_login_at = now();
        $user->save();

        $request->session()->forget('setup_login');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('success', 'Пароль задан, добро пожаловать!');
    }

    /** Смена своего пароля. */
    public function showChangePassword()
    {
        return view('bonus::admin.password_change', ['rules' => User::passwordRulesMessage()]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => User::passwordRules(),
        ], ['password.regex' => User::passwordRulesMessage()]);

        $user = $request->user();
        $user->password = $data['password'];
        $user->save();

        return redirect()->route('admin.dashboard')->with('success', 'Пароль изменён');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
