@extends('bonus::admin.layout')
@section('title', 'Карта ' . $card->short_code)
@section('content')
@php($canEdit = auth()->user()->canEdit())
@php($actions = \Solt\Bonus\Http\Controllers\Admin\OperationController::ACTIONS)
<p><a href="{{ route('admin.cards') }}" class="muted">← Карты</a></p>
<h1>Карта <code>{{ $card->short_code }}</code> @include('bonus::admin.partials.card_status', ['card' => $card])</h1>

<div class="stats">
  <div class="stat"><span class="muted">Баланс</span><b>{{ number_format($balance, 2, ',', ' ') }}</b></div>
  @foreach ($wallets as $w)
    <div class="stat"><span class="muted">{{ $w['legal_entity']?->inn ?? 'без юрлица' }}<br><small>{{ $w['legal_entity']?->name }}</small></span><b>{{ number_format($w['sum'], 2, ',', ' ') }}</b></div>
  @endforeach
</div>

<div class="card" style="margin-top:18px;">
  <table>
    <tr><th style="width:220px;">ФИО</th><td>{{ $card->lastname }} {{ $card->firstname }} {{ $card->name }}</td></tr>
    <tr><th>Телефон / e-mail</th><td>{{ $card->phone ?: '—' }} {{ $card->email ? ' / ' . $card->email : '' }}</td></tr>
    <tr><th>Дата рождения / пол / город</th><td>{{ $card->birthdate ? \Carbon\Carbon::parse($card->birthdate)->format('d.m.Y') : '—' }} / {{ \Solt\Bonus\Models\Card::$genders[$card->gender] ?? '—' }} / {{ $card->city ?: '—' }}</td></tr>
    <tr><th>Юрлицо-владелец</th><td>{{ $card->legal_entity ? $card->legal_entity->inn . ' — ' . $card->legal_entity->name : '—' }}</td></tr>
    <tr><th>GUID (1С) / рассылка</th><td>{{ $card->guid ?: '—' }} / {{ $card->newsletter_agree ? 'да' : 'нет' }}</td></tr>
    <tr><th>Создана / изменена</th><td>{{ $card->created_at?->format('d.m.Y H:i') }} / {{ $card->updated_at?->format('d.m.Y H:i') }}</td></tr>
  </table>
  @if ($ledger_errors)
    <div class="flash error" style="margin-top:12px;">
      Разрыв цепочки остатков: {{ count($ledger_errors) }} строк(и). Первая — операция №{{ $ledger_errors[0]['id'] }}, поле {{ $ledger_errors[0]['field'] }}: должно быть {{ $ledger_errors[0]['expected'] }}, записано {{ $ledger_errors[0]['actual'] }}.
      @if (auth()->user()->isAdmin())
      <form method="post" action="{{ route('admin.cards.rebuild', $card) }}" style="margin-top:10px;" onsubmit="return confirm('Пересчитать остатки по суммам операций? Суммы не изменятся, поля «остаток» будут перезаписаны во всех строках карты. Действие записывается в журнал.');">
        @csrf
        <button class="btn danger" type="submit">Пересчитать остатки по суммам</button>
        <span class="muted" style="font-size:13px;">Истиной считаются суммы операций. Если ошибочна именно сумма — сначала исправьте её корректировкой, пересчёт делайте после.</span>
      </form>
      @endif
    </div>
  @endif
</div>

@if ($canEdit)
<div class="card">
  <h2 style="margin-top:0;">Корректировка баланса</h2>
  <form method="post" action="{{ route('admin.cards.adjust', $card) }}" class="row">
    @csrf
    <div class="field"><label>Сумма (+ начислить, − списать)</label><input type="text" name="amount" value="{{ old('amount') }}" required style="width:140px;" placeholder="-50 или 100"></div>
    <div class="field"><label>Юрлицо (кошелёк)</label>
      <select name="legal_entity_id" required>
        @foreach ($legal_entities as $le)<option value="{{ $le->id }}" @selected((string) $le->id === old('legal_entity_id', (string) $card->legal_entity_id))>{{ $le->inn }} — {{ $le->name }}</option>@endforeach
      </select>
    </div>
    <div class="field" style="flex:1;"><label>Причина (обязательно)</label><input type="text" name="comment" value="{{ old('comment') }}" required minlength="3" placeholder="Обращение клиента №…, ошибка кассира…"></div>
    <button class="btn" type="submit" onclick="return confirm('Записать корректировку в журнал?');">Записать</button>
  </form>
  <p class="muted" style="margin-bottom:0;">Корректировка — обычная строка журнала с типом документа «Корректировка», вашим логином и причиной. Удалить её нельзя, только отменить следующей корректировкой.</p>
</div>

<div class="card">
  <h2 style="margin-top:0;">Карта</h2>
  <div class="row">
    @if ((int) $card->status === 0 && !$card->deleted_at)
      <form method="post" action="{{ route('admin.cards.block', $card) }}" class="row" onsubmit="return confirm('Заблокировать карту {{ $card->short_code }}? Кассы перестанут её принимать.');">
        @csrf
        <div class="field"><label>Причина блокировки</label><input type="text" name="reason" style="width:260px;"></div>
        <button class="btn danger" type="submit">Заблокировать</button>
      </form>
      <form method="post" action="{{ route('admin.cards.replace', $card) }}" class="row" onsubmit="return confirm('Перенести весь баланс на новую карту и пометить эту как «заменена»?');">
        @csrf
        <div class="field"><label>Заменить на карту №</label><input type="text" name="new_short_code" value="{{ old('new_short_code') }}" style="width:200px;" required></div>
        <button class="btn secondary" type="submit">Заменить</button>
      </form>
    @elseif ((int) $card->status === 1)
      <form method="post" action="{{ route('admin.cards.unblock', $card) }}">@csrf<button class="btn" type="submit">Разблокировать</button></form>
    @else
      <span class="muted">Карта заменена — баланс перенесён на другую карту, действий нет.</span>
    @endif
  </div>
</div>
@endif

<div class="card">
  <h2 style="margin-top:0;">История операций</h2>
  <table>
    <thead><tr><th>№</th><th>Дата док.</th><th>Записано</th><th>Вид</th><th>Документ</th><th>ИНН</th><th>Аптека</th><th class="num">Сумма</th><th class="num">Остаток</th><th></th></tr></thead>
    <tbody>
    @forelse ($operations as $op)
      <tr style="{{ $op->deleted_at ? 'opacity:.55;' : '' }}">
        <td>{{ $op->id }}</td>
        <td>{{ \Carbon\Carbon::parse($op->document_date)->format('d.m.Y') }}</td>
        <td class="muted">{{ $op->created_at?->format('d.m.Y H:i') }}</td>
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
      @if ($op->comment)<tr><td></td><td colspan="9" class="muted" style="padding-top:0;">{{ $op->comment }}</td></tr>@endif
    @empty
      <tr><td colspan="10" class="muted">Операций нет.</td></tr>
    @endforelse
    </tbody>
  </table>
  {{ $operations->links('bonus::admin.pagination') }}
</div>
@endsection
