@extends('bonus::admin.layout')
@section('title', 'Юрлица')
@section('content')
<h1>Юрлица</h1>
<div class="card">
  <table>
    @php($canEdit = auth()->user()->canEdit())
    <thead><tr><th>ИНН</th><th>Название</th><th class="num">Аптек</th><th class="num">Карт (владелец)</th>@if ($canEdit)<th></th>@endif</tr></thead>
    <tbody>
    @forelse ($legal_entities as $le)
      <tr>
        <td><code>{{ $le->inn }}</code></td>
        <td>
          @if ($canEdit)
          <form method="post" action="{{ route('admin.legal-entities.update', $le) }}" class="row" style="gap:6px;">
            @csrf
            <input type="text" name="name" value="{{ $le->name }}" required style="width:320px;">
            <button class="btn small secondary" type="submit">Сохранить</button>
          </form>
          @else{{ $le->name }}@endif
        </td>
        <td class="num">{{ $pharmacy_counts[$le->id] ?? 0 }}</td>
        <td class="num">{{ $card_counts[$le->id] ?? 0 }}</td>
        @if ($canEdit)
        <td style="text-align:right;">
          @if (($pharmacy_counts[$le->id] ?? 0) > 0)
            <span class="muted" title="Сначала переведите аптеки на другое юрлицо">есть аптеки</span>
          @else
            <form method="post" action="{{ route('admin.legal-entities.destroy', $le) }}" onsubmit="return confirm('Удалить юрлицо {{ $le->inn }} «{{ addslashes($le->name) }}»?');">
              @csrf @method('DELETE')
              <button class="btn small danger" type="submit">Удалить</button>
            </form>
          @endif
        </td>
        @endif
      </tr>
    @empty
      <tr><td colspan="5" class="muted">Юрлиц нет.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

@if ($canEdit)
<div class="card">
  <h2 style="margin-top:0;">Добавить юрлицо</h2>
  <form method="post" action="{{ route('admin.legal-entities.store') }}" class="row">
    @csrf
    <div class="field"><label>ИНН (10–12 цифр)</label><input type="text" name="inn" value="{{ old('inn') }}" pattern="[0-9]{10,12}" required style="width:180px;"></div>
    <div class="field" style="flex:1;"><label>Название</label><input type="text" name="name" value="{{ old('name') }}" required></div>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>
@endif

<p class="muted">Удалить можно только юрлицо без аптек. Карты и операции удалённого юрлица остаются в журнале и в отчётах.</p>
@endsection
