@extends('bonus::admin.layout')
@section('title', 'Кассы (клиенты API)')
@section('content')
<h1>Кассы — доступ к API</h1>

@if (session('new_client'))
<div class="card" style="border-color:#a6d8b4; background:#f3fbf5;">
  <h2 style="margin-top:0;">Данные для 1С — «{{ session('new_client')['name'] }}»</h2>
  <table>
    <tr><th style="width:140px;">Client ID</th><td><code>{{ session('new_client')['id'] }}</code></td></tr>
    <tr><th>Client Secret</th><td><code>{{ session('new_client')['secret'] }}</code></td></tr>
  </table>
  <p class="flash warn" style="margin:12px 0 0;">Секрет показывается <b>только сейчас</b>. Скопируйте его в настройки 1С. Если потеряете — нажмите «Новый секрет».</p>
</div>
@endif

<div class="card">
  <table>
    <thead><tr><th>Название</th><th>Client ID</th><th>Создана</th><th>Состояние</th><th></th></tr></thead>
    <tbody>
    @forelse ($clients as $c)
      <tr>
        <td>{{ $c->name }}</td>
        <td><code>{{ $c->getKey() }}</code></td>
        <td class="muted">{{ $c->created_at?->format('d.m.Y H:i') }}</td>
        <td>@if ($c->revoked)<span class="badge err">отозвана</span>@else<span class="badge ok">действует</span>@endif</td>
        <td style="text-align:right; white-space:nowrap;">
          @unless ($c->revoked)
          <form method="post" action="{{ route('admin.clients.secret', $c) }}" style="display:inline;" onsubmit="return confirm('Выпустить новый секрет для «{{ $c->name }}»? Старый перестанет работать, его нужно заменить в 1С.');">@csrf<button class="btn small secondary" type="submit">Новый секрет</button></form>
          <form method="post" action="{{ route('admin.clients.revoke', $c) }}" style="display:inline;" onsubmit="return confirm('Отозвать «{{ $c->name }}»? Все её токены перестанут действовать.');">@csrf<button class="btn small danger" type="submit">Отозвать</button></form>
          @endunless
        </td>
      </tr>
    @empty
      <tr><td colspan="5" class="muted">Клиентов нет.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="card">
  <h2 style="margin-top:0;">Новая касса (клиент API)</h2>
  <p class="muted">Каждая запись — отдельная пара Client ID / Secret для получения токена (<code>POST /oauth/token</code>, grant_type=client_credentials). Можно завести одну на всю сеть, по одной на аптеку или отдельную для тестов — отзыв одной не трогает остальные.</p>
  <form method="post" action="{{ route('admin.clients.store') }}" class="row">
    @csrf
    <div class="field" style="flex:1;"><label>Название</label><input type="text" name="name" value="{{ old('name') }}" required placeholder="Кассы аптеки 5 / Тест 1С"></div>
    <button class="btn" type="submit">Создать</button>
  </form>
</div>
@endsection
