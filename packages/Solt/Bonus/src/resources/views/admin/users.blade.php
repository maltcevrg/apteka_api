@extends('bonus::admin.layout')
@section('title', 'Пользователи')
@section('content')
<h1>Пользователи</h1>
<div class="card">
  <table>
    <thead><tr><th>Логин</th><th>Имя</th><th>Роль</th><th>Состояние</th><th>Последний вход</th><th></th></tr></thead>
    <tbody>
    @foreach ($users as $u)
      <tr>
        <td><code>{{ $u->login }}</code>@if ($u->id === auth()->id()) <span class="muted">(вы)</span>@endif</td>
        <td>{{ $u->name }}</td>
        <td>
          <form method="post" action="{{ route('admin.users.role', $u) }}" class="row" style="gap:6px;">
            @csrf
            <select name="role" @disabled($u->id === auth()->id())>
              @foreach ($roles as $key => $label)<option value="{{ $key }}" @selected($u->role === $key)>{{ $label }}</option>@endforeach
            </select>
            @if ($u->id !== auth()->id())<button class="btn small secondary" type="submit">Сохранить</button>@endif
          </form>
        </td>
        <td>@if ($u->needsPassword())<span style="color:var(--warn);">ждёт первого входа</span>@else<span style="color:var(--ok);">активен</span>@endif</td>
        <td class="muted">{{ $u->last_login_at?->format('d.m.Y H:i') ?? '—' }}</td>
        <td style="white-space:nowrap; text-align:right;">
          @if (!$u->needsPassword())
          <form method="post" action="{{ route('admin.users.reset', $u) }}" style="display:inline;" onsubmit="return confirm('Сбросить пароль «{{ $u->login }}»? Пользователь задаст новый при следующем входе.');">
            @csrf<button class="btn small secondary" type="submit">Сбросить пароль</button>
          </form>
          @endif
          @if ($u->id !== auth()->id())
          <form method="post" action="{{ route('admin.users.destroy', $u) }}" style="display:inline;" onsubmit="return confirm('Удалить пользователя «{{ $u->login }}»?');">
            @csrf @method('DELETE')<button class="btn small danger" type="submit">Удалить</button>
          </form>
          @endif
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

<div class="card">
  <h2 style="margin-top:0;">Новый пользователь</h2>
  <p class="muted">Пароль не задаётся здесь: сообщите человеку логин, при первом входе он придумает пароль сам.</p>
  <form method="post" action="{{ route('admin.users.store') }}" class="row">
    @csrf
    <div class="field"><label>Логин</label><input type="text" name="login" value="{{ old('login') }}" pattern="[a-zA-Z0-9_.@-]{2,64}" required style="width:200px;"></div>
    <div class="field" style="flex:1;"><label>Имя (необязательно)</label><input type="text" name="name" value="{{ old('name') }}"></div>
    <div class="field"><label>Роль</label>
      <select name="role">@foreach ($roles as $key => $label)<option value="{{ $key }}" @selected(old('role', 'user') === $key)>{{ $label }}</option>@endforeach</select>
    </div>
    <button class="btn" type="submit">Создать</button>
  </form>
</div>

<div class="card">
  <h2 style="margin-top:0;">Роли</h2>
  <table>
    <tr><td><b>Администратор</b></td><td>всё, включая пользователей и роли</td></tr>
    <tr><td><b>Редактор</b></td><td>аптеки, юрлица, загрузка из 1С, отчёты</td></tr>
    <tr><td><b>Пользователь</b></td><td>только просмотр и отчёты</td></tr>
  </table>
</div>
@endsection
