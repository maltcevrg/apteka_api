<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Пользователи админ-панели (только для роли admin).
 */
class UserController extends Controller
{
    public function index()
    {
        return view('bonus::admin.users', [
            'users' => User::orderBy('role')->orderBy('login')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-zA-Z0-9_.@-]+$/', 'unique:users,login'],
            'name'  => ['nullable', 'string', 'max:255'],
            'role'  => ['required', Rule::in(array_keys(User::ROLES))],
        ], ['login.regex' => 'Логин: латинские буквы, цифры, . _ @ -']);

        User::create([
            'login'    => $data['login'],
            'name'     => $data['name'] ?: $data['login'],
            'role'     => $data['role'],
            'password' => null,
        ]);

        return back()->with('success', "Пользователь «{$data['login']}» создан (" . User::ROLES[$data['role']] . "). Сообщите ему логин — пароль он задаст при первом входе.");
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(User::ROLES))]]);

        if ($user->isAdmin() && $data['role'] !== User::ROLE_ADMIN && $this->isLastAdmin($user)) {
            return back()->withErrors(['Нельзя снять роль администратора с последнего администратора']);
        }

        $user->role = $data['role'];
        $user->save();

        return back()->with('success', "«{$user->login}»: роль — " . $user->roleName());
    }

    /** Сброс пароля: при следующем входе пользователь задаст новый. */
    public function resetPassword(Request $request, User $user)
    {
        $user->password = null;
        $user->remember_token = null;
        $user->save();

        return back()->with('success', "«{$user->login}»: пароль сброшен. При следующем входе по логину пользователь задаст новый.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['Нельзя удалить самого себя']);
        }
        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            return back()->withErrors(['Нельзя удалить последнего администратора']);
        }

        $user->delete();
        return back()->with('success', "Пользователь «{$user->login}» удалён");
    }

    private function isLastAdmin(User $user): bool
    {
        return User::where('role', User::ROLE_ADMIN)->where('id', '!=', $user->id)->doesntExist();
    }
}
