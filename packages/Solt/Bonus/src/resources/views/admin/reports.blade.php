@extends('bonus::admin.layout')
@section('title', 'Отчёты')
@section('content')
<h1>Отчёты</h1>
<div class="card">
  <h2 style="margin-top:0;">Период</h2>
  <div class="presets">
    @foreach ($presets as $key => $p)
      <a href="{{ route('admin.reports', ['preset' => $key]) }}" class="{{ $preset === $key ? 'active' : '' }}">{{ $p[0] }}</a>
    @endforeach
  </div>
  <form method="get" action="{{ route('admin.reports') }}" class="row">
    <div class="field"><label>С</label><input type="date" name="from" value="{{ $from }}" required></div>
    <div class="field"><label>По</label><input type="date" name="to" value="{{ $to }}" required></div>
    <button class="btn secondary" type="submit">Применить</button>
  </form>
  <p class="muted" style="margin-bottom:0;">Выбран период: <b>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</b> (по дате документа).</p>
</div>

<div class="card">
  <h2 style="margin-top:0;">Доступные отчёты</h2>
  <p class="muted">Состав и форма отчётов — по отдельному ТЗ. Планируемый перечень (см. docs/20):</p>
  <table>
    <thead><tr><th>Отчёт</th><th>Параметры</th><th>Состояние</th></tr></thead>
    <tbody>
      <tr><td>Срез остатков по картам и ИНН</td><td>на дату «По»</td><td class="muted">в разработке</td></tr>
      <tr><td>Обороты по юрлицам</td><td>период С — По</td><td class="muted">в разработке</td></tr>
      <tr><td>Движения по карте</td><td>карта, период С — По</td><td class="muted">в разработке</td></tr>
      <tr><td>Сверка остатков с 1С</td><td>файл карта;остаток, дата</td><td class="muted">в разработке</td></tr>
    </tbody>
  </table>
</div>
@endsection
