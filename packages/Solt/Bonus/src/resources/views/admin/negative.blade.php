@extends('bonus::admin.layout')
@section('title', 'Карты в минусе')
@section('content')
<h1>Карты с отрицательным балансом</h1>
<div class="card">
  <div class="row" style="justify-content:space-between;">
    <div>Карт: <b>{{ $list->count() }}</b>, суммарный минус: <b class="neg">{{ number_format($total, 2, ',', ' ') }}</b></div>
    @if ($list->isNotEmpty())<a class="btn secondary" href="{{ route('admin.cards.negative', ['csv' => 1]) }}">Скачать CSV</a>@endif
  </div>
</div>
<div class="card">
  <table>
    <thead><tr><th>Карта</th><th>ФИО</th><th>Телефон</th><th>Юрлицо</th><th class="num">Баланс</th><th class="num">Операций</th><th>Последний документ</th><th>Статус</th></tr></thead>
    <tbody>
    @forelse ($list as $r)
      @php($c = $r['card'])
      <tr>
        <td><a href="{{ route('admin.cards.show', $c) }}"><code>{{ $c->short_code }}</code></a></td>
        <td>{{ $c->lastname }} {{ $c->firstname }} {{ $c->name }}</td>
        <td>{{ $c->phone }}</td>
        <td>{{ $c->legal_entity?->inn }}</td>
        <td class="num neg">{{ number_format($r['balance'], 2, ',', ' ') }}</td>
        <td class="num">{{ $r['ops'] }}</td>
        <td>{{ $r['last_date'] ? \Carbon\Carbon::parse($r['last_date'])->format('d.m.Y') : '' }}</td>
        <td>@include('bonus::admin.partials.card_status', ['card' => $c])</td>
      </tr>
    @empty
      <tr><td colspan="8" class="muted">Карт с отрицательным балансом нет.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
