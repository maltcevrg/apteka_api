@extends('bonus::admin.layout')
@section('title', 'Действия в панели')
@section('content')
<h1>Действия в панели</h1>
<div class="card">
  <form method="get" action="{{ route('admin.log') }}" class="row">
    <div class="field"><label>С</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div class="field"><label>По</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="field"><label>Действие</label><select name="action"><option value="">все</option>@foreach ($actions as $k => $v)<option value="{{ $k }}" @selected(($filters['action'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label>Пользователь</label><select name="login"><option value="">все</option>@foreach ($logins as $l)<option value="{{ $l }}" @selected(($filters['login'] ?? '') === $l)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn" type="submit">Показать</button>
    <a class="btn secondary" href="{{ route('admin.log') }}">Сбросить</a>
  </form>
</div>
<div class="card">
  <table>
    <thead><tr><th>Когда</th><th>Кто</th><th>Действие</th><th>Объект</th><th>Подробности</th><th>IP</th></tr></thead>
    <tbody>
    @forelse ($logs as $l)
      <tr>
        <td class="muted" style="white-space:nowrap;">{{ $l->created_at->format('d.m.Y H:i:s') }}</td>
        <td>{{ $l->login ?? '—' }}</td>
        <td>{{ $l->actionName() }}</td>
        <td>{{ $l->subject }}</td>
        <td class="muted" style="font-size:13px;">
          @if ($l->details)
            @if (isset($l->details['output']))<details><summary>вывод</summary><pre>{{ $l->details['output'] }}</pre></details>
            @else{{ collect($l->details)->map(fn ($v, $k) => $k . ': ' . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v))->implode('; ') }}@endif
          @endif
        </td>
        <td class="muted">{{ $l->ip }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Записей нет.</td></tr>
    @endforelse
    </tbody>
  </table>
  {{ $logs->links('bonus::admin.pagination') }}
</div>
@endsection
