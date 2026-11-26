@extends('bonus::admin.layout')
@section('title', 'Карты')
@section('content')
<h1>Карты</h1>
<div class="card">
  <form method="get" action="{{ route('admin.cards') }}" class="row">
    <div class="field" style="flex:1;"><label>Номер карты, телефон, фамилия или GUID</label><input type="text" name="q" value="{{ $q }}" autofocus placeholder="DISN32569 / 9139465878 / Денисова"></div>
    <button class="btn" type="submit">Найти</button>
  </form>
</div>
@if ($cards !== null)
<div class="card">
  @if ($cards->isEmpty())
    <p class="muted">Ничего не найдено по запросу «{{ $q }}».</p>
  @else
  <table>
    <thead><tr><th>Карта</th><th>ФИО</th><th>Телефон</th><th>Город</th><th>Юрлицо</th><th>Статус</th></tr></thead>
    <tbody>
    @foreach ($cards as $c)
      <tr>
        <td><a href="{{ route('admin.cards.show', $c) }}"><code>{{ $c->short_code }}</code></a></td>
        <td>{{ $c->firstname }} {{ $c->name }} {{ $c->lastname }}</td>
        <td>{{ $c->phone }}</td>
        <td>{{ $c->city }}</td>
        <td>{{ $c->legal_entity?->inn }}</td>
        <td>@include('bonus::admin.partials.card_status', ['card' => $c])</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @if ($cards->count() >= 100)<p class="muted">Показаны первые 100 — уточните запрос.</p>@endif
  @endif
</div>
@endif
@endsection
