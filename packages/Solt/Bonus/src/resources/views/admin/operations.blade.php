@extends('bonus::admin.layout')
@section('title', 'Журнал операций')
@section('content')
<h1>Журнал операций</h1>
<div class="card">
  <form method="get" action="{{ route('admin.operations') }}" class="row">
    <div class="field"><label>С</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div class="field"><label>По</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="field"><label>Аптека</label><select name="pharmacy_id"><option value="">все</option>@foreach ($pharmacies as $p)<option value="{{ $p->id }}" @selected(($filters['pharmacy_id'] ?? '') == $p->id)>{{ $p->number }} — {{ $p->name }}</option>@endforeach</select></div>
    <div class="field"><label>Юрлицо</label><select name="legal_entity_id"><option value="">все</option>@foreach ($legal_entities as $le)<option value="{{ $le->id }}" @selected(($filters['legal_entity_id'] ?? '') == $le->id)>{{ $le->inn }} — {{ $le->name }}</option>@endforeach</select></div>
    <div class="field"><label>Вид</label><select name="action"><option value="">все</option>@foreach ($actions as $k => $v)<option value="{{ $k }}" @selected(isset($filters['action']) && $filters['action'] !== null && (string) $filters['action'] === (string) $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label>Документ №</label><input type="text" name="document_number" value="{{ $filters['document_number'] ?? '' }}" style="width:130px;"></div>
    <div class="field"><label>Карта</label><input type="text" name="short_code" value="{{ $filters['short_code'] ?? '' }}" style="width:130px;"></div>
    <div class="field"><label>&nbsp;</label><label><input type="checkbox" name="offline" value="1" @checked(!empty($filters['offline']))> офлайн</label></div>
    <div class="field"><label>&nbsp;</label><label><input type="checkbox" name="partial" value="1" @checked(!empty($filters['partial']))> частичные</label></div>
    <div class="field"><label>&nbsp;</label><label><input type="checkbox" name="manual" value="1" @checked(!empty($filters['manual']))> ручные</label></div>
    <button class="btn" type="submit">Показать</button>
    <a class="btn secondary" href="{{ route('admin.operations') }}">Сбросить</a>
  </form>
</div>

<div class="card">
  <div class="row" style="justify-content:space-between;">
    <div>
      <b>Проверка цепочки остатков:</b>
      @if ($last_check)
        <span class="badge {{ ($last_check->details['ok'] ?? false) ? 'ok' : 'err' }}">{{ ($last_check->details['ok'] ?? false) ? 'целая' : 'разрывы' }}</span>
        <span class="muted">{{ $last_check->created_at->format('d.m.Y H:i') }}, {{ $last_check->login }}</span>
      @else<span class="muted">ещё не выполнялась</span>@endif
    </div>
    @if (auth()->user()->canEdit())
    <form method="post" action="{{ route('admin.operations.check') }}">@csrf<button class="btn secondary" type="submit">Проверить сейчас</button></form>
    @endif
  </div>
  @if (session('success') || session('error'))<pre style="margin-top:10px;">{{ session('success') ?? session('error') }}</pre>@endif
</div>

<div class="card">
  <table>
    <thead><tr><th>№</th><th>Дата док.</th><th>Записано</th><th>Карта</th><th>Вид</th><th>Документ</th><th>ИНН</th><th>Апт.</th><th class="num">Сумма</th><th class="num">Остаток</th><th></th></tr></thead>
    <tbody>
    @forelse ($operations as $op)
      <tr style="{{ $op->deleted_at ? 'opacity:.55;' : '' }}">
        <td>{{ $op->id }}</td>
        <td>{{ \Carbon\Carbon::parse($op->document_date)->format('d.m.Y') }}@if ($op->document_time) <span class="muted">{{ substr($op->document_time, 0, 5) }}</span>@endif</td>
        <td class="muted">{{ $op->created_at?->format('d.m.Y H:i') }}</td>
        <td>@if ($op->card)<a href="{{ route('admin.cards.show', $op->card) }}"><code>{{ $op->card->short_code }}</code></a>@endif</td>
        <td>{{ $actions[$op->action] ?? $op->action }}@if ($op->parent_id) <span class="muted">→ №{{ $op->parent_id }}</span>@endif</td>
        <td>{{ $op->document_type }} {{ $op->document_number }}</td>
        <td>{{ $op->legal_entity?->inn }}</td>
        <td>{{ $op->pharmacy?->number }}</td>
        <td class="num {{ $op->amount < 0 ? 'neg' : 'pos' }}">{{ number_format($op->amount, 2, ',', ' ') }}@if ($op->amount_requested !== null)<br><small class="muted">запрошено {{ number_format($op->amount_requested, 2, ',', ' ') }}</small>@endif</td>
        <td class="num">{{ number_format($op->card_after, 2, ',', ' ') }}</td>
        <td>
          @if ($op->offline)<span class="badge warn">офлайн</span>@endif
          @if ($op->deleted_at)<span class="badge">погашена</span>@endif
          @if ($op->user)<span class="badge" title="{{ $op->comment }}">{{ $op->user->login }}</span>@endif
        </td>
      </tr>
    @empty
      <tr><td colspan="11" class="muted">Ничего не найдено.</td></tr>
    @endforelse
    </tbody>
  </table>
  {{ $operations->links('bonus::admin.pagination') }}
</div>
@endsection
