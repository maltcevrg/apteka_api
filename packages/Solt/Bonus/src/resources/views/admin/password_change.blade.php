@extends('bonus::admin.layout')
@section('title', 'Смена пароля')
@section('content')
<div class="card" style="max-width:440px;">
  <h1>Смена пароля</h1>
  <form method="post" action="{{ route('admin.password.change.post') }}">
    @csrf
    <div class="field" style="margin-bottom:12px;">
      <label for="current_password">Текущий пароль</label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
    </div>
    <div class="field" style="margin-bottom:12px;">
      <label for="password">Новый пароль</label>
      <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8" maxlength="64">
    </div>
    <div class="field" style="margin-bottom:12px;">
      <label for="password_confirmation">Ещё раз</label>
      <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <p class="muted" style="font-size:13px;">{{ $rules }}</p>
    <button class="btn" type="submit">Сменить пароль</button>
  </form>
</div>
@endsection
