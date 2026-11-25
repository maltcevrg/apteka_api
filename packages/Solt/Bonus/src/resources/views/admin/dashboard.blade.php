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
  <h2 style="margin-top:0;">Последняя операция</h2>
  @if ($last_operation)
    <p>№{{ $last_operation->id }} от {{ $last_operation->created_at?->format('d.m.Y H:i') }} —
      документ «{{ $last_operation->document_type }}» {{ $last_operation->document_number }},
      сумма <b>{{ number_format($last_operation->amount, 2, ',', ' ') }}</b>,
      остаток карты после: {{ number_format($last_operation->card_after, 2, ',', ' ') }}</p>
  @else
    <p class="muted">Операций пока нет.</p>
  @endif
</div>
<div class="card">
  <h2 style="margin-top:0;">Разделы</h2>
  <ul>
    <li><a href="{{ route('admin.pharmacies') }}">Аптеки</a>{{ auth()->user()->canEdit() ? ' — добавление, удаление, смена ИНН (юрлица).' : ' — просмотр.' }}</li>
    <li><a href="{{ route('admin.legal-entities') }}">Юрлица</a>{{ auth()->user()->canEdit() ? ' — добавление и удаление.' : ' — просмотр.' }}</li>
    @if (auth()->user()->canEdit())<li><a href="{{ route('admin.import') }}">Загрузка из 1С</a> — справочники и карты с остатками из CSV.</li>@endif
    <li><a href="{{ route('admin.reports') }}">Отчёты</a> — выбор периода; состав отчётов — по отдельному ТЗ.</li>
    @if (auth()->user()->isAdmin())<li><a href="{{ route('admin.users') }}">Пользователи</a> — учётные записи и роли.</li>@endif
    <li><a href="{{ route('admin.password.change') }}">Сменить свой пароль</a>.</li>
  </ul>
</div>
@endsection
