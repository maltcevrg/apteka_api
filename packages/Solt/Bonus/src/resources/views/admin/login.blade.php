@extends('bonus::admin.layout')
@section('title', 'Вход')
@section('content')
<div class="card" style="max-width:380px; margin:60px auto;">
  <h1>Вход в админ-панель</h1>
  <form method="post" action="{{ route('admin.login.post') }}">
    @csrf
    <div class="field" style="margin-bottom:12px;">
      <label for="login">Логин</label>
      <input type="text" id="login" name="login" value="{{ old('login') }}" autofocus autocomplete="username" required>
    </div>
    <div class="field" style="margin-bottom:16px;">
      <label for="password">Пароль</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <label style="display:block; margin-bottom:16px;"><input type="checkbox" name="remember" value="1"> Запомнить меня</label>
    <button class="btn" type="submit" style="width:100%;">Войти</button>
  </form>
</div>
@endsection
