@extends('bonus::admin.layout')
@section('title', 'Обзор')
@section('content')
<h1>Обзор</h1>
<div class="stats">
  <div class="stat"><span class="muted">Юрлиц</span><b>{{ $counts['legal_entities'] }}</b></div>
  <div class="stat"><span class="muted">Аптек</span><b>{{ $counts['pharmacies'] }}</b></div>
  <div class="stat"><span class="muted">Карт</span><b>{{ $counts['cards'] }}</b></div>
  <div class="stat"><span class="muted">Операций в журнале</span><b>{{ $counts['operations'] }}</b></div>
</div>

<div class="card" style="margin-top:18px;">
  <h2 style="margin-top:0;">Состояние системы</h2>
  <table>
    <tr><th style="width:260px;">Последняя операция от касс</th>
      <td>@if ($last_operation)
          {{ $last_operation->created_at?->format('d.m.Y H:i') }}
          @if ($last_operation->created_at && $last_operation->created_at->lt(now()->subHours(12)))<span class="badge warn">более 12 часов назад</span>@else<span class="badge ok">недавно</span>@endif
          — «{{ $last_operation->document_type }}» {{ $last_operation->document_number }}, {{ number_format($last_operation->amount, 2, ',', ' ') }}
          @if ($last_operation->card)(<a href="{{ route('admin.cards.show', $last_operation->card) }}">{{ $last_operation->card->short_code }}</a>)@endif
        @else<span class="muted">операций пока нет</span>@endif</td></tr>
    <tr><th>Последний токен выдан кассе</th><td>{{ $last_token ? \Carbon\Carbon::parse($last_token)->format('d.m.Y H:i') : '—' }}</td></tr>
    <tr><th>Сегодня</th><td>{{ $today['count'] }} операций: начислено {{ number_format($today['increase'], 2, ',', ' ') }}, списано {{ number_format(abs($today['decrease']), 2, ',', ' ') }}
        @if ($today['offline'])· офлайн {{ $today['offline'] }}@endif @if ($today['partial'])· <span class="neg">частично проведено {{ $today['partial'] }}</span>@endif</td></tr>
    <tr><th>Проверка цепочки остатков</th>
      <td>@if ($last_check)<span class="badge {{ ($last_check->details['ok'] ?? false) ? 'ok' : 'err' }}">{{ ($last_check->details['ok'] ?? false) ? 'целая' : 'разрывы' }}</span> <span class="muted">{{ $last_check->created_at->format('d.m.Y H:i') }}</span>@else<span class="muted">не выполнялась</span>@endif
        — <a href="{{ route('admin.operations') }}">журнал</a></td></tr>
    <tr><th>Ошибки в логе за сутки</th>
      <td>@if ($log_errors['count'] === 0)<span class="badge ok">нет</span>@else<span class="badge err">{{ $log_errors['count'] }}</span>
        <details><summary>последние</summary><pre>{{ implode("\n", $log_errors['lines']) }}</pre></details>@endif</td></tr>
  </table>
</div>

@if (auth()->user()->isAdmin() && $recent_admin->isNotEmpty())
<div class="card">
  <h2 style="margin-top:0;">Последние действия в панели <a href="{{ route('admin.log') }}" class="muted" style="font-size:13px; font-weight:normal;">все →</a></h2>
  <table>
    @foreach ($recent_admin as $l)
      <tr><td class="muted" style="white-space:nowrap;">{{ $l->created_at->format('d.m H:i') }}</td><td>{{ $l->login }}</td><td>{{ $l->actionName() }}</td><td>{{ $l->subject }}</td></tr>
    @endforeach
  </table>
</div>
@endif

<div class="card">
  <h2 style="margin-top:0;">Разделы</h2>
  <ul>
    <li><a href="{{ route('admin.cards') }}">Карты</a> — поиск, баланс, история{{ auth()->user()->canEdit() ? ', корректировка, блокировка, замена' : '' }}.</li>
    <li><a href="{{ route('admin.operations') }}">Журнал</a> — все операции с фильтрами, проверка цепочки остатков.</li>
    <li><a href="{{ route('admin.pharmacies') }}">Аптеки</a>{{ auth()->user()->canEdit() ? ' — добавление, удаление, смена ИНН (юрлица).' : ' — просмотр.' }}</li>
    <li><a href="{{ route('admin.legal-entities') }}">Юрлица</a>{{ auth()->user()->canEdit() ? ' — добавление и удаление.' : ' — просмотр.' }}</li>
    @if (auth()->user()->canEdit())<li><a href="{{ route('admin.import') }}">Загрузка из 1С</a> — справочники и карты с остатками из CSV.</li>@endif
    <li><a href="{{ route('admin.reports') }}">Отчёты</a>.</li>
    @if (auth()->user()->isAdmin())
      <li><a href="{{ route('admin.users') }}">Пользователи</a> — учётные записи и роли.</li>
      <li><a href="{{ route('admin.clients') }}">Кассы</a> — доступ касс к API (Client ID / Secret).</li>
      <li><a href="{{ route('admin.log') }}">Действия</a> — кто и что менял в панели.</li>
    @endif
    <li><a href="{{ route('admin.password.change') }}">Сменить свой пароль</a>.</li>
  </ul>
</div>
@endsection
