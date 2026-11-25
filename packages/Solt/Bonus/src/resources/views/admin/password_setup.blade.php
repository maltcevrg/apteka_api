@extends('bonus::admin.layout')
@section('title', 'Создание пароля')
@section('content')
<div class="card" style="max-width:440px; margin:60px auto;">
  <h1>Первый вход</h1>
  <p>Здравствуйте, <b>{{ $user->name }}</b> (логин <code>{{ $user->login }}</code>, {{ $user->roleName() }}). Придумайте пароль — дальше вы будете входить по логину и этому паролю.</p>
  <form method="post" action="{{ route('admin.password.setup.post') }}">
    @csrf
    <div class="field" style="margin-bottom:12px;">
      <label for="password">Пароль</label>
      <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8" maxlength="64" autofocus>
    </div>
    <div class="field" style="margin-bottom:12px;">
      <label for="password_confirmation">Ещё раз</label>
      <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <p class="muted" style="font-size:13px;">{{ $rules }}</p>
    <button class="btn" type="submit" style="width:100%;">Сохранить и войти</button>
  </form>
  <p style="margin-top:14px;"><a href="{{ route('admin.login') }}" class="muted">← Это не я</a></p>
</div>
@endsection
